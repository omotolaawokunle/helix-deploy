<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Build;

use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Steps\BaseBuildStep;

final class PrepareBuildDirectoryStep extends BaseBuildStep
{
    public function name(): string
    {
        return 'prepare-build-directory';
    }

    public function run(BuildContext $ctx): void
    {
        $this->runCommand($ctx, 'mkdir -p '.$this->shellQuote($ctx->buildPath));
    }

    public function rollback(BuildContext $ctx): void
    {
        $this->runCommand($ctx, 'rm -rf '.$this->shellQuote($ctx->buildPath));
    }
}
