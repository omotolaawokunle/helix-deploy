<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Python;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class InstallPythonDepsStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'install-python-deps';
    }

    public function run(DeploymentContext $ctx): void
    {
        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($ctx->releasePath).' && pip install -r requirements.txt',
        );
    }
}
