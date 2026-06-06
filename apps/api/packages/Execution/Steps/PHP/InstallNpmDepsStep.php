<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\PHP;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class InstallNpmDepsStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'install-npm-deps';
    }

    public function run(DeploymentContext $ctx): void
    {
        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($ctx->releasePath).' && npm ci',
        );
    }

    public function isSkippable(DeploymentContext $ctx): bool
    {
        $result = $ctx->ssh->run('test -f '.$this->shellQuote($ctx->releasePath.'/package.json'));

        return $result->exitCode !== 0;
    }
}
