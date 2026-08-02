<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Docker;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;
use Throwable;

final class DockerCleanupStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'docker-cleanup';
    }

    public function run(DeploymentContext $ctx): void
    {
        // Best-effort only: a slow/failed prune must never fail an otherwise
        // successful deploy (SSH default timeout used to abort here after 30s).
        try {
            $this->runCommand(
                $ctx,
                'docker image prune -f',
                $this->longCommandTimeoutSeconds(),
            );
            $this->runCommand(
                $ctx,
                'docker container prune -f',
                $this->longCommandTimeoutSeconds(),
            );
        } catch (Throwable $exception) {
            $ctx->log('docker-cleanup warning: '.$exception->getMessage());
        }
    }
}
