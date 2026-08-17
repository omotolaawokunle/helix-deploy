<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Models\Organization;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Servers\Models\Server;
use App\Packages\Provisioning\Enums\PostgresqlVersion;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InstallPostgreSQL extends BaseProvisioningScript
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
        private readonly Organization $organization,
        private readonly PostgresqlVersion $configuredVersion = PostgresqlVersion::V16,
    ) {}

    public function name(): string
    {
        return 'postgresql';
    }

    public function description(): string
    {
        return 'Installs PostgreSQL and provisions deploy role.';
    }

    public function estimatedMinutes(): int
    {
        return 3;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(SSHConnectionInterface $ssh, Server $server, array $options = []): void
    {
        $this->prepare($options);

        $configuredVersion = isset($options['postgresqlVersion'])
            ? PostgresqlVersion::tryFrom((string) $options['postgresqlVersion'])
            : $this->configuredVersion;

        if ($configuredVersion === null) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported PostgreSQL version. Allowed values: %s.',
                implode(', ', PostgresqlVersion::values()),
            ));
        }

        $version = $configuredVersion->value;
        $package = "postgresql-{$version}";

        if ($this->commandExists($ssh, 'psql')) {
            $this->logInfo($options, 'postgresql already installed — skipping package installation');
            $this->runStep($ssh, 'systemctl enable postgresql', 'enable-postgresql');
            $this->runStep($ssh, 'systemctl start postgresql', 'start-postgresql');
            $this->grantDeployRolePrivileges($ssh);

            return;
        }

        $password = Str::random(32);
        $escapedPassword = str_replace("'", "''", $password);

        $this->ensurePostgresqlAptRepository($ssh);
        $this->runStep($ssh, $this->apt('apt-get update -y'), 'apt-update');
        $this->runStep($ssh, $this->apt("apt-get install -y {$package}"), 'install-postgresql');
        $this->runStep($ssh, 'systemctl enable postgresql', 'enable-postgresql');
        $this->runStep($ssh, 'systemctl start postgresql', 'start-postgresql');
        $this->runStep(
            $ssh,
            "sudo -u postgres psql -tc \"SELECT 1 FROM pg_roles WHERE rolname='deploy'\" | grep -q 1 || sudo -u postgres psql -c \"CREATE ROLE deploy LOGIN CREATEDB PASSWORD '{$escapedPassword}';\"",
            'create-postgres-role',
        );
        $this->grantDeployRolePrivileges($ssh);

        $this->credentialVault->storeServerSecret(
            organization: $this->organization,
            owner: $server,
            name: sprintf('%s-postgresql-deploy-password', $server->hostname),
            value: $password,
        );
    }

    /**
     * PostgreSQL 15+ revoked CREATE on schema public from PUBLIC, so Laravel
     * migrate as the deploy role fails unless we grant it on template1 (inherited
     * by CREATE DATABASE) and every existing database.
     */
    private function grantDeployRolePrivileges(SSHConnectionInterface $ssh): void
    {
        $this->runStep(
            $ssh,
            <<<'SHELL'
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='deploy'" | grep -q 1 || exit 0
sudo -u postgres psql -c "ALTER ROLE deploy WITH LOGIN CREATEDB;"
for db in template1 $(sudo -u postgres psql -Atqc "SELECT datname FROM pg_database WHERE datistemplate = false AND datallowconn"); do
  sudo -u postgres psql -d "$db" -c "GRANT CONNECT, TEMP, CREATE ON DATABASE \"$db\" TO deploy;"
  sudo -u postgres psql -d "$db" -c "GRANT USAGE, CREATE ON SCHEMA public TO deploy;"
  sudo -u postgres psql -d "$db" -c "GRANT ALL ON ALL TABLES IN SCHEMA public TO deploy;"
  sudo -u postgres psql -d "$db" -c "GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO deploy;"
  sudo -u postgres psql -d "$db" -c "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO deploy;"
  sudo -u postgres psql -d "$db" -c "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO deploy;"
done
SHELL
            ,
            'grant-postgres-deploy-role',
        );
    }

    private function ensurePostgresqlAptRepository(SSHConnectionInterface $ssh): void
    {
        $this->runStep($ssh, $this->apt('apt-get install -y curl ca-certificates gnupg lsb-release'), 'install-postgresql-prereqs');
        $this->runStep(
            $ssh,
            <<<'SHELL'
install -d /usr/share/postgresql-common/pgdg
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.gpg
. /etc/os-release
echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.gpg] https://apt.postgresql.org/pub/repos/apt ${VERSION_CODENAME}-pgdg main" > /etc/apt/sources.list.d/pgdg.list
SHELL,
            'add-postgresql-pgdg-repo',
        );
    }
}
