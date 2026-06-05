<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\RunnerDeploy;

use App\Packages\Artifacts\ArtifactManager;
use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class VerifyTargetArtifactChecksumStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'verify-target-artifact-checksum';
    }

    public function run(DeploymentContext $ctx): void
    {
        if ($ctx->artifact === null) {
            throw new \RuntimeException('Build artifact is required for checksum verification.');
        }

        app(ArtifactManager::class)->verify(
            checksum: $ctx->artifact->checksum,
            ssh: $ctx->ssh,
            remotePath: $ctx->artifact->storage_path,
        );
    }
}
