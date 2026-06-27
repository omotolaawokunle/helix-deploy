<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Modules\Servers\Models\Server;
use App\Packages\Provisioning\Enums\PythonVersion;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use InvalidArgumentException;

class InstallPython extends BaseProvisioningScript
{
    public function __construct(
        private readonly PythonVersion $configuredVersion = PythonVersion::V3_12,
    ) {
    }

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

        $configuredVersion = isset($options['pythonVersion'])
            ? PythonVersion::tryFrom((string) $options['pythonVersion'])
            : $this->configuredVersion;

        if ($configuredVersion === null) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported Python version. Allowed values: %s.',
                implode(', ', PythonVersion::values()),
            ));
        }

        $version = $configuredVersion->value;
        $binary = 'python'.$version;
        $pipBinary = 'pip'.$version;

        if ($this->commandExists($ssh, $binary)) {
            $this->logInfo($options, "{$binary} already installed — skipping package installation");

            if (! $this->commandExists($ssh, 'gunicorn')) {
                $this->runStep($ssh, "{$pipBinary} install gunicorn uvicorn", 'install-python-processes');
            }

            return;
        }

        $this->ensurePythonAptRepository($ssh);
        $this->runStep($ssh, $this->apt('apt-get update -y'), 'apt-update');
        $this->runStep(
            $ssh,
            $this->apt("apt-get install -y {$binary} {$binary}-venv {$binary}-dev"),
            'install-python',
        );
        $this->runStep($ssh, "{$pipBinary} install gunicorn uvicorn", 'install-python-processes');
    }

    private function ensurePythonAptRepository(SSHConnectionInterface $ssh): void
    {
        $this->runStep($ssh, $this->apt('apt-get install -y software-properties-common'), 'install-python-prereqs');
        $this->runStep($ssh, $this->apt('add-apt-repository -y ppa:deadsnakes/ppa'), 'add-deadsnakes-ppa');
    }
}
