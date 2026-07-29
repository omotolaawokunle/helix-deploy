<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Docker;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class DockerComposeUpStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'docker-compose-up';
    }

    public function run(DeploymentContext $ctx): void
    {
        $composePath = DockerComposePath::resolveOrDefault($ctx);

        $this->runCommand(
            $ctx,
            DockerComposeCli::command($composePath, 'up -d'),
        );
    }
}
