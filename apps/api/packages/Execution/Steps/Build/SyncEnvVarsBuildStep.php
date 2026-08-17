<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Build;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Services\EnvFileManager;
use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Steps\BaseBuildStep;
use RuntimeException;

final class SyncEnvVarsBuildStep extends BaseBuildStep
{
    public function name(): string
    {
        return 'sync-env-vars';
    }

    public function run(BuildContext $ctx): void
    {
        $organization = Organization::query()->find($ctx->site->organization_id);

        if ($organization === null) {
            throw new RuntimeException('Site organization not found.');
        }

        $remotePath = $this->workPath($ctx).'/.env';

        $ctx->log('Writing environment variables into the build directory');

        $manager = app(EnvFileManager::class);
        $manager->writeTo($ctx->site, $organization, $ctx->ssh, $remotePath);

        $this->runCommand($ctx, 'chmod 600 '.$this->shellQuote($remotePath));
    }
}
