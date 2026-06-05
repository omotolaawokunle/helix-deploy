<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Build;

use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Steps\BaseBuildStep;

final class InstallNpmDepsBuildStep extends BaseBuildStep
{
    public function name(): string
    {
        return 'install-npm-deps';
    }

    public function run(BuildContext $ctx): void
    {
        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($this->workPath($ctx)).' && npm ci',
        );
    }

    public function isSkippable(BuildContext $ctx): bool
    {
        $result = $ctx->ssh->run('test -f '.$this->shellQuote($this->workPath($ctx).'/package.json'));

        return $result->exitCode !== 0;
    }
}
