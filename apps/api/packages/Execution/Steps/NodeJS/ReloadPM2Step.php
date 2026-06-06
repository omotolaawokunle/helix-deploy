<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\NodeJS;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class ReloadPM2Step extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'reload-pm2';
    }

    public function run(DeploymentContext $ctx): void
    {
        $domain = $ctx->site->domain;

        $this->runCommand($ctx, 'pm2 reload '.$this->shellQuote($domain));
    }
}
