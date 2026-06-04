<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Docker;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class DockerLoginStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'docker-login';
    }

    public function run(DeploymentContext $ctx): void
    {
        $registry = $ctx->site->docker_registry;

        if ($registry === null || $registry === '') {
            return;
        }

        $this->runCommand($ctx, 'docker login '.$this->shellQuote($registry));
    }

    public function isSkippable(DeploymentContext $ctx): bool
    {
        $registry = $ctx->site->docker_registry;

        return $registry === null || $registry === '';
    }
}
