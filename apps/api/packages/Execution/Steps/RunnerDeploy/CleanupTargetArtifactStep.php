<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\RunnerDeploy;

use App\Packages\Artifacts\ArtifactManager;
use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class CleanupTargetArtifactStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'cleanup-target-artifact';
    }

    public function run(DeploymentContext $ctx): void
    {
        if ($ctx->artifact === null) {
            return;
        }

        app(ArtifactManager::class)->cleanup($ctx->ssh, $ctx->artifact->storage_path);
    }
}
