<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

class InstallDocker extends BaseProvisioningScript
{
    public function name(): string
    {
        return 'docker';
    }

    public function description(): string
    {
        return 'Installs Docker engine and Docker Compose plugin.';
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

        $this->runStep($ssh, 'curl -fsSL https://get.docker.com | sh', 'install-docker');
        $this->runStep($ssh, 'usermod -aG docker deploy', 'add-deploy-to-docker-group');
        $this->runStep($ssh, $this->apt('apt-get install -y docker-compose-plugin'), 'install-docker-compose-plugin');
    }
}
