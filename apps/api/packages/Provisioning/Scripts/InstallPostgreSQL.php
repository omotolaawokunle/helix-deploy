<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Models\Organization;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use Illuminate\Support\Str;

class InstallPostgreSQL extends BaseProvisioningScript
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
        private readonly Organization $organization,
    ) {
    }

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
     * @param array<string, mixed> $options
     */
    public function handle(SSHConnectionInterface $ssh, Server $server, array $options = []): void
    {
        $this->prepare($options);

        if ($this->commandExists($ssh, 'psql')) {
            $this->logInfo($options, 'postgresql already installed — skipping package installation');
            $this->runStep($ssh, 'systemctl enable postgresql', 'enable-postgresql');
            $this->runStep($ssh, 'systemctl start postgresql', 'start-postgresql');

            return;
        }

        $password = Str::random(32);
        $escapedPassword = str_replace("'", "''", $password);

        $this->runStep($ssh, $this->apt('apt-get update -y'), 'apt-update');
        $this->runStep($ssh, $this->apt('apt-get install -y postgresql-16'), 'install-postgresql');
        $this->runStep($ssh, 'systemctl enable postgresql', 'enable-postgresql');
        $this->runStep($ssh, 'systemctl start postgresql', 'start-postgresql');
        $this->runStep(
            $ssh,
            "sudo -u postgres psql -tc \"SELECT 1 FROM pg_roles WHERE rolname='deploy'\" | grep -q 1 || sudo -u postgres psql -c \"CREATE ROLE deploy LOGIN PASSWORD '{$escapedPassword}';\"",
            'create-postgres-role',
        );

        $this->credentialVault->storeSecret(
            organization: $this->organization,
            owner: $server,
            name: sprintf('%s-postgresql-deploy-password', $server->hostname),
            value: $password,
        );
    }
}
