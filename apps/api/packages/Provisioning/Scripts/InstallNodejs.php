<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Packages\Provisioning\Enums\NodejsVersion;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use InvalidArgumentException;

class InstallNodejs extends BaseProvisioningScript
{
    public function __construct(
        private readonly NodejsVersion $configuredVersion = NodejsVersion::V20,
    ) {
    }

    public function name(): string
    {
        return 'nodejs';
    }

    public function description(): string
    {
        return 'Installs Node.js from NodeSource and PM2.';
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

        $configuredVersion = isset($options['nodeVersion'])
            ? NodejsVersion::tryFrom((int) $options['nodeVersion'])
            : $this->configuredVersion;

        if ($configuredVersion === null) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported Node.js version. Allowed values: %s.',
                implode(', ', NodejsVersion::values()),
            ));
        }

        $version = $configuredVersion->value;

        $this->runStep($ssh, $this->apt('apt-get update -y'), 'apt-update');
        $this->runStep($ssh, $this->apt('apt-get install -y ca-certificates curl gnupg'), 'install-node-prereqs');
        $this->runStep($ssh, "curl -fsSL https://deb.nodesource.com/setup_{$version}.x | bash -", 'nodesource-setup');
        $this->runStep($ssh, $this->apt('apt-get install -y nodejs'), 'install-nodejs');
        $this->runStep($ssh, 'npm install -g pm2', 'install-pm2');
    }
}
