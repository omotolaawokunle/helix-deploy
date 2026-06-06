<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\PHP;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class ReloadPHPFPMStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'reload-php-fpm';
    }

    public function run(DeploymentContext $ctx): void
    {
        $version = $ctx->site->php_version ?? '8.3';
        $service = 'php'.$version.'-fpm';

        $this->runCommand($ctx, 'sudo systemctl reload '.$service);
    }
}
