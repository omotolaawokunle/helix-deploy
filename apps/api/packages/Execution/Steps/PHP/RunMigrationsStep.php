<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\PHP;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;
use App\Packages\Execution\Support\ArtisanMigrateShellCommand;

final class RunMigrationsStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'run-migrations';
    }

    public function run(DeploymentContext $ctx): void
    {
        $ctx->site->loadMissing('environment');

        if ($ctx->site->environment?->is_production === true) {
            $ctx->log('WARNING: running database migrations on production');
        }

        $this->runCommand($ctx, ArtisanMigrateShellCommand::forReleasePath($ctx->releasePath));
    }

    public function isSkippable(DeploymentContext $ctx): bool
    {
        return ! $ctx->site->run_migrations;
    }
}
