<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\PHP;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class InstallComposerDepsStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'install-composer-deps';
    }

    public function run(DeploymentContext $ctx): void
    {
        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($ctx->releasePath).' && composer install --no-dev --optimize-autoloader --no-interaction',
        );
    }
}
