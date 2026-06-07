<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Models\Organization;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use Illuminate\Support\Str;

class InstallMySQL extends BaseProvisioningScript
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
        private readonly Organization $organization,
    ) {
    }

    public function name(): string
    {
        return 'mysql';
    }

    public function description(): string
    {
        return 'Installs MySQL Server and provisions deploy database user.';
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

        if ($this->commandExists($ssh, 'mysql')) {
            $this->logInfo($options, 'mysql already installed — skipping package installation');
            $this->runStep($ssh, 'systemctl enable mysql', 'enable-mysql');
            $this->runStep($ssh, 'systemctl start mysql', 'start-mysql');

            return;
        }

        $password = Str::random(32);
        $escapedPassword = escapeshellarg($password);

        $this->runStep($ssh, $this->apt('apt-get update -y'), 'apt-update');
        $this->runStep($ssh, $this->apt('apt-get install -y mysql-server-8.0'), 'install-mysql');
        $this->runStep($ssh, 'systemctl enable mysql', 'enable-mysql');
        $this->runStep($ssh, 'systemctl start mysql', 'start-mysql');
        $this->runStep(
            $ssh,
            "mysql -u root -e \"CREATE USER IF NOT EXISTS 'deploy'@'localhost' IDENTIFIED BY {$escapedPassword};\"",
            'create-mysql-deploy-user',
        );
        $this->runStep(
            $ssh,
            "mysql -u root -e \"GRANT ALL PRIVILEGES ON *.* TO 'deploy'@'localhost' WITH GRANT OPTION; FLUSH PRIVILEGES;\"",
            'grant-mysql-deploy-user',
        );

        $this->credentialVault->storeSecret(
            organization: $this->organization,
            owner: $server,
            name: sprintf('%s-mysql-deploy-password', $server->hostname),
            value: $password,
        );
    }
}
