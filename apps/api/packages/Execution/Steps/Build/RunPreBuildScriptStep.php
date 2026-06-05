<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Build;

use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Steps\BaseBuildStep;

final class RunPreBuildScriptStep extends BaseBuildStep
{
    public function name(): string
    {
        return 'run-pre-build-script';
    }

    public function run(BuildContext $ctx): void
    {
        $script = $ctx->site->pre_build_script;

        if (! is_string($script) || trim($script) === '') {
            return;
        }

        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($this->workPath($ctx)).' && '.$script,
            600,
        );
    }

    public function isSkippable(BuildContext $ctx): bool
    {
        $script = $ctx->site->pre_build_script;

        return ! is_string($script) || trim($script) === '';
    }
}
