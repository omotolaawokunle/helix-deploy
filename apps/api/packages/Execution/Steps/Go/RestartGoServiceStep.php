<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Go;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class RestartGoServiceStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'restart-go-service';
    }

    public function run(DeploymentContext $ctx): void
    {
        $service = $ctx->site->go_service_name ?? $ctx->site->domain;

        $this->runCommand($ctx, 'sudo systemctl restart '.$this->shellQuote($service));
    }
}
