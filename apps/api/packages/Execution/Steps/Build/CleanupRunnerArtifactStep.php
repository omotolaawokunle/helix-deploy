<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Build;

use App\Packages\Artifacts\ArtifactManager;
use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Steps\BaseBuildStep;

final class CleanupRunnerArtifactStep extends BaseBuildStep
{
    public function name(): string
    {
        return 'cleanup-runner-artifact';
    }

    public function run(BuildContext $ctx): void
    {
        app(ArtifactManager::class)->cleanup($ctx->ssh, $ctx->artifactPath);
        $this->runCommand($ctx, 'rm -rf '.$this->shellQuote($ctx->buildPath));
    }
}
