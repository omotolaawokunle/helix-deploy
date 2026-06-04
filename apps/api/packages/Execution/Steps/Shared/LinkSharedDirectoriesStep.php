<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Shared;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class LinkSharedDirectoriesStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'link-shared-directories';
    }

    public function run(DeploymentContext $ctx): void
    {
        $links = [
            $ctx->sharedPath.'/.env' => $ctx->releasePath.'/.env',
            $ctx->sharedPath.'/storage' => $ctx->releasePath.'/storage',
        ];

        foreach ($links as $shared => $release) {
            $this->runCommand($ctx, sprintf(
                'ln -sfn %s %s',
                $this->shellQuote($shared),
                $this->shellQuote($release),
            ));
        }
    }
}
