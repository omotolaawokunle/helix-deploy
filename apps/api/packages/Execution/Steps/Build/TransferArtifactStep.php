<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Build;

use App\Packages\Artifacts\Transfers\ScpArtifactTransfer;
use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Steps\BaseBuildStep;

final class TransferArtifactStep extends BaseBuildStep
{
    public function name(): string
    {
        return 'transfer-artifact';
    }

    public function run(BuildContext $ctx): void
    {
        if ($ctx->artifact === null) {
            throw new \RuntimeException('Build artifact must be created before transfer.');
        }

        if ($ctx->targetSsh === null || $ctx->targetServer === null) {
            throw new \RuntimeException('Target server SSH connection is required for artifact transfer.');
        }

        (new ScpArtifactTransfer($ctx->targetServer))->transfer(
            runnerSsh: $ctx->ssh,
            targetSsh: $ctx->targetSsh,
            runnerPath: $ctx->artifactPath,
            targetPath: $ctx->artifactPath,
            expectedChecksum: $ctx->artifact->checksum,
        );
    }
}
