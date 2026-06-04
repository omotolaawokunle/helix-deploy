<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Docker;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class DockerCleanupStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'docker-cleanup';
    }

    public function run(DeploymentContext $ctx): void
    {
        $this->runCommand($ctx, 'docker image prune -f');
        $this->runCommand($ctx, 'docker container prune -f');
    }
}
