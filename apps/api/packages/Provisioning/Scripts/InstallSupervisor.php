<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

class InstallSupervisor extends BaseProvisioningScript
{
    public function name(): string
    {
        return 'supervisor';
    }

    public function description(): string
    {
        return 'Installs and enables Supervisor.';
    }

    public function estimatedMinutes(): int
    {
        return 1;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function handle(SSHConnectionInterface $ssh, Server $server, array $options = []): void
    {
        $this->prepare($options);

        if ($this->commandExists($ssh, 'supervisorctl')) {
            $this->logInfo($options, 'supervisor already installed — skipping package installation');
            $this->runStep($ssh, 'systemctl enable supervisor', 'enable-supervisor');
            $this->runStep($ssh, 'systemctl start supervisor', 'start-supervisor');

            return;
        }

        $this->runStep($ssh, $this->apt('apt-get update -y'), 'apt-update');
        $this->runStep($ssh, $this->apt('apt-get install -y supervisor'), 'install-supervisor');
        $this->runStep($ssh, 'systemctl enable supervisor', 'enable-supervisor');
        $this->runStep($ssh, 'systemctl start supervisor', 'start-supervisor');
    }
}
