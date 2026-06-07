<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Packages\Provisioning\Enums\PhpVersion;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use InvalidArgumentException;

class InstallPHP extends BaseProvisioningScript
{
    public function __construct(
        private readonly PhpVersion $configuredVersion = PhpVersion::V8_3,
    ) {
    }

    public function name(): string
    {
        return 'php';
    }

    public function description(): string
    {
        return 'Installs PHP-FPM, common extensions, and Composer.';
    }

    public function estimatedMinutes(): int
    {
        return 4;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function handle(SSHConnectionInterface $ssh, Server $server, array $options = []): void
    {
        $this->prepare($options);

        $configuredVersion = isset($options['phpVersion'])
            ? PhpVersion::tryFrom((string) $options['phpVersion'])
            : $this->configuredVersion;

        if ($configuredVersion === null) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported PHP version. Allowed values: %s.',
                implode(', ', PhpVersion::values()),
            ));
        }

        $version = $configuredVersion->value;
        $fpmPackage = "php{$version}-fpm";

        if ($this->packageInstalled($ssh, $fpmPackage)) {
            $this->logInfo($options, "{$fpmPackage} already installed — skipping package installation");
        } else {
            $packages = [
                $fpmPackage,
                "php{$version}-mbstring",
                "php{$version}-xml",
                "php{$version}-curl",
                "php{$version}-zip",
                "php{$version}-bcmath",
                "php{$version}-mysql",
                "php{$version}-pgsql",
                "php{$version}-redis",
                "php{$version}-gd",
                "php{$version}-intl",
                "php{$version}-sqlite3",
                'composer',
            ];

            $this->runStep($ssh, $this->apt('apt-get install -y software-properties-common'), 'install-apt-prereqs');
            $this->runStep($ssh, $this->apt('add-apt-repository -y ppa:ondrej/php'), 'add-php-ppa');
            $this->runStep($ssh, $this->apt('apt-get update -y'), 'apt-update-php');
            $this->runStep($ssh, $this->apt('apt-get install -y '.implode(' ', $packages)), 'install-php-packages');
        }

        $this->runStep($ssh, "systemctl enable --now php{$version}-fpm", 'enable-php-fpm');

        if ($this->commandExists($ssh, 'nginx')) {
            $this->preventApachePortConflict($ssh);
        }
    }
}
