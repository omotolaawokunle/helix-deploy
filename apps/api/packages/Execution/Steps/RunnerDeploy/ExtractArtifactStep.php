<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\RunnerDeploy;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class ExtractArtifactStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'extract-artifact';
    }

    public function run(DeploymentContext $ctx): void
    {
        if ($ctx->artifact === null) {
            throw new \RuntimeException('Build artifact is required to extract on the target server.');
        }

        $artifactPath = $ctx->artifact->storage_path;

        $this->runCommand($ctx, 'mkdir -p '.$this->shellQuote($ctx->releasePath));
        $this->runCommand($ctx, sprintf(
            'tar -xzf %s -C %s',
            $this->shellQuote($artifactPath),
            $this->shellQuote($ctx->releasePath),
        ));
    }

    public function rollback(DeploymentContext $ctx): void
    {
        $this->runCommand($ctx, 'rm -rf '.$this->shellQuote($ctx->releasePath));
    }
}
