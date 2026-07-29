<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Shared;

use App\Modules\Sites\Services\SharedStorageBootstrap;
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
        $owner = $this->webrootOwner($ctx);
        $sharedStorage = $ctx->sharedPath.'/storage';

        app(SharedStorageBootstrap::class)->ensureReady($ctx->ssh, $sharedStorage, $owner);

        $links = [
            $ctx->sharedPath.'/.env' => $ctx->releasePath.'/.env',
            $sharedStorage => $ctx->releasePath.'/storage',
        ];

        foreach ($links as $shared => $release) {
            // Git clones include storage/ and sometimes .env as real paths; ln -sfn into
            // an existing directory creates a nested link instead of replacing it.
            $this->runCommand($ctx, sprintf(
                'sudo rm -rf %s && sudo ln -sfn %s %s',
                $this->shellQuote($release),
                $this->shellQuote($shared),
                $this->shellQuote($release),
            ));
        }
    }

    private function webrootOwner(DeploymentContext $ctx): string
    {
        $sshUser = trim((string) $ctx->server->ssh_user);

        return $sshUser !== '' ? $sshUser : 'deploy';
    }
}
