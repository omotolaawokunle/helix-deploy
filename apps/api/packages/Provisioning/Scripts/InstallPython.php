<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

class InstallPython extends BaseProvisioningScript
{
    public function name(): string
    {
        return 'python';
    }

    public function description(): string
    {
        return 'Installs Python runtime, pip, and app server dependencies.';
    }

    public function estimatedMinutes(): int
    {
        return 2;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function handle(SSHConnectionInterface $ssh, Server $server, array $options = []): void
    {
        $this->prepare($options);

        if ($this->commandExists($ssh, 'python3')) {
            $this->logInfo($options, 'python3 already installed — skipping package installation');

            if (! $this->commandExists($ssh, 'gunicorn')) {
                $this->runStep($ssh, 'pip3 install gunicorn uvicorn', 'install-python-processes');
            }

            return;
        }

        $this->runStep($ssh, $this->apt('apt-get update -y'), 'apt-update');
        $this->runStep($ssh, $this->apt('apt-get install -y python3 python3-pip python3-venv'), 'install-python');
        $this->runStep($ssh, 'pip3 install gunicorn uvicorn', 'install-python-processes');
    }
}
