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
        $composePath = $ctx->site->docker_compose_path ?? $ctx->sharedPath.'/docker-compose.yml';
        $directory = dirname($composePath);

        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($directory).' && docker compose -f '.$this->shellQuote($composePath).' up -d',
        );
    }
}
