<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Python;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class CollectStaticStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'collect-static';
    }

    public function run(DeploymentContext $ctx): void
    {
        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($ctx->releasePath).' && python manage.py collectstatic --noinput',
        );
    }

    public function isSkippable(DeploymentContext $ctx): bool
    {
        $manage = $ctx->ssh->run('test -f '.$this->shellQuote($ctx->releasePath.'/manage.py'));

        return $manage->exitCode !== 0;
    }
}
