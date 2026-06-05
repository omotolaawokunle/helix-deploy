<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Build;

use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Steps\BaseBuildStep;

final class VerifyBuildRunnerStep extends BaseBuildStep
{
    public function name(): string
    {
        return 'verify-build-runner';
    }

    public function run(BuildContext $ctx): void
    {
        $this->runCommand($ctx, 'echo "_helix_runner_ok_" && uname -a');
    }
}
