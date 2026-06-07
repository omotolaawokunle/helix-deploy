<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Modules\Servers\Models\Server;
use App\Packages\Provisioning\Contracts\ProvisioningScriptInterface;
use App\Packages\Provisioning\Exceptions\ProvisioningStepFailedException;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

abstract class BaseProvisioningScript implements ProvisioningScriptInterface
{
    /**
     * @var callable(string): void|null
     */
    private $lineCallback = null;

    public function isIdempotent(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $options
     */
    abstract public function handle(SSHConnectionInterface $ssh, Server $server, array $options = []): void;

    protected function runStep(SSHConnectionInterface $ssh, string $command, string $stepName): void
    {
        $result = $ssh->run($command, $this->lineCallback);

        if ($result->failed()) {
            throw new ProvisioningStepFailedException(sprintf(
                '[%s] failed: %s',
                $stepName,
                $result->output(),
            ));
        }
    }

    protected function apt(string $command): string
    {
        if (preg_match('/apt-get\s+install\b/', $command) === 1
            && ! str_contains($command, '--no-install-recommends')) {
            $command = preg_replace(
                '/apt-get\s+install\b/',
                'apt-get install --no-install-recommends',
                $command,
                1,
            ) ?? $command;
        }

        return 'export DEBIAN_FRONTEND=noninteractive && '.$command;
    }

    protected function commandExists(SSHConnectionInterface $ssh, string $command): bool
    {
        $result = $ssh->run(sprintf(
            'command -v %s >/dev/null 2>&1 && echo yes || echo no',
            escapeshellarg($command),
        ));

        return trim($result->stdout) === 'yes';
    }

    protected function packageInstalled(SSHConnectionInterface $ssh, string $package): bool
    {
        $result = $ssh->run(sprintf(
            "dpkg -s %s 2>/dev/null | grep -q '^Status: install ok installed' && echo yes || echo no",
            escapeshellarg($package),
        ));

        return trim($result->stdout) === 'yes';
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function logInfo(array $options, string $message): void
    {
        if (isset($options['lineCallback']) && is_callable($options['lineCallback'])) {
            ($options['lineCallback'])($message);
        }
    }

    protected function preventApachePortConflict(SSHConnectionInterface $ssh): void
    {
        $this->runStep(
            $ssh,
            <<<'SHELL'
if systemctl list-unit-files apache2.service >/dev/null 2>&1; then
  systemctl stop apache2 2>/dev/null || true
  systemctl disable apache2 2>/dev/null || true
fi
SHELL,
            'disable-apache-for-nginx',
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function prepare(array $options): void
    {
        $this->lineCallback = isset($options['lineCallback']) && is_callable($options['lineCallback'])
            ? $options['lineCallback']
            : null;
    }
}
