<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Shared;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Services\EnvFileManager;
use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;
use RuntimeException;

final class SyncEnvVarsStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'sync-env-vars';
    }

    public function run(DeploymentContext $ctx): void
    {
        $organization = Organization::query()->find($ctx->site->organization_id);

        if ($organization === null) {
            throw new RuntimeException('Site organization not found.');
        }

        $ctx->log('Syncing environment variables to shared/.env');

        app(EnvFileManager::class)->sync($ctx->site, $organization, $ctx->ssh);
    }
}
