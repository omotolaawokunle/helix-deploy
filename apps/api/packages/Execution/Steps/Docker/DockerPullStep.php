<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Docker;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class DockerPullStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'docker-pull';
    }

    public function run(DeploymentContext $ctx): void
    {
        $image = $ctx->site->docker_image;

        if ($image === null || $image === '') {
            throw new \RuntimeException('Site docker_image is required for docker pull deployments');
        }

        $this->runCommand($ctx, 'docker pull '.$this->shellQuote($image));
    }
}
