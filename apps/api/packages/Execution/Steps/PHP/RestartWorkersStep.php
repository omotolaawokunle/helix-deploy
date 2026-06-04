<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\PHP;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class RestartWorkersStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'restart-workers';
    }

    public function run(DeploymentContext $ctx): void
    {
        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($ctx->releasePath).' && php artisan horizon:terminate',
        );
    }

    public function isSkippable(DeploymentContext $ctx): bool
    {
        $horizon = $ctx->ssh->run('test -f '.$this->shellQuote($ctx->releasePath.'/config/horizon.php'));

        return $horizon->exitCode !== 0;
    }
}
