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
        return 'export DEBIAN_FRONTEND=noninteractive && '.$command;
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
