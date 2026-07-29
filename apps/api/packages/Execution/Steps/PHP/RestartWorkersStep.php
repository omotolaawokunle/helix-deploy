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
        $release = $this->shellQuote($ctx->releasePath);

        if ($this->hasHorizon($ctx)) {
            $this->runCommand($ctx, 'cd '.$release.' && php artisan horizon:terminate');

            return;
        }

        $this->runCommand($ctx, 'cd '.$release.' && php artisan queue:restart');
    }

    private function hasHorizon(DeploymentContext $ctx): bool
    {
        // Prefer the installed package over config/horizon.php — config can remain
        // after composer --no-dev strips laravel/horizon from vendor.
        $horizon = $ctx->ssh->run(
            'test -d '.$this->shellQuote($ctx->releasePath.'/vendor/laravel/horizon'),
        );

        return $horizon->exitCode === 0;
    }
}
