<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Shared;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class ReloadNginxStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'reload-nginx';
    }

    public function run(DeploymentContext $ctx): void
    {
        $this->runCommand($ctx, 'sudo nginx -t');
        $this->runCommand($ctx, 'sudo systemctl reload nginx');
    }
}
