<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Build;

use App\Packages\Artifacts\ArtifactManager;
use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Steps\BaseBuildStep;

final class CreateArtifactStep extends BaseBuildStep
{
    public function name(): string
    {
        return 'create-artifact';
    }

    public function run(BuildContext $ctx): void
    {
        $artifact = app(ArtifactManager::class)->create(
            ssh: $ctx->ssh,
            buildPath: $ctx->buildPath,
            artifactPath: $ctx->artifactPath,
            runner: $ctx->runner,
            deployment: $ctx->deployment,
        );

        $ctx->artifact = $artifact;

        $ctx->deployment->forceFill([
            'build_artifact_id' => (string) $artifact->getKey(),
        ])->save();
    }
}
