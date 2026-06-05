<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Shared;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class RunPostDeployScriptStep extends BaseDeploymentStep
{
    private const TIMEOUT_SECONDS = 600;

    public function name(): string
    {
        return 'run-post-deploy-script';
    }

    public function run(DeploymentContext $ctx): void
    {
        $script = $ctx->site->post_deploy_script;

        if ($script === null || $script === '') {
            return;
        }

        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($ctx->releasePath).' && '.$script,
            self::TIMEOUT_SECONDS,
        );
    }

    public function isSkippable(DeploymentContext $ctx): bool
    {
        $script = $ctx->site->post_deploy_script;

        return $script === null || $script === '';
    }
}
