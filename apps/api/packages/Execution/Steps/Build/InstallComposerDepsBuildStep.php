<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Build;

use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Steps\BaseBuildStep;
use App\Packages\Execution\Support\PhpBinary;

final class InstallComposerDepsBuildStep extends BaseBuildStep
{
    public function name(): string
    {
        return 'install-composer-deps';
    }

    public function run(BuildContext $ctx): void
    {
        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($this->workPath($ctx)).' && '.PhpBinary::composerInstall($ctx->site),
        );
    }
}
