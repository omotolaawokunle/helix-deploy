<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Docker;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class DockerBuildStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'docker-build';
    }

    public function run(DeploymentContext $ctx): void
    {
        $composePath = DockerComposePath::resolve($ctx);

        if ($composePath !== null) {
            $this->runCommand(
                $ctx,
                DockerComposeCli::command(
                    $composePath,
                    'build',
                    rtrim($ctx->sharedPath, '/').'/.env',
                    $ctx->site->resolvedComposeProjectName(),
                ),
            );

            return;
        }

        $tag = $ctx->site->docker_image ?? $ctx->site->domain.':latest';

        $this->runCommand(
            $ctx,
            'docker build -t '.$this->shellQuote($tag).' '.$this->shellQuote($ctx->releasePath),
        );
    }
}
