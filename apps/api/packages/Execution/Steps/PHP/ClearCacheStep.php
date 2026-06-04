<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\PHP;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class ClearCacheStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'clear-cache';
    }

    public function run(DeploymentContext $ctx): void
    {
        $path = $this->shellQuote($ctx->releasePath);

        $this->runCommand($ctx, 'cd '.$path.' && php artisan config:cache');
        $this->runCommand($ctx, 'cd '.$path.' && php artisan route:cache');
        $this->runCommand($ctx, 'cd '.$path.' && php artisan view:cache');
    }
}
