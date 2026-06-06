<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\NodeJS;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class InstallNpmDepsNodeStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'install-npm-deps-node';
    }

    public function run(DeploymentContext $ctx): void
    {
        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($ctx->releasePath).' && npm ci',
        );
    }
}
