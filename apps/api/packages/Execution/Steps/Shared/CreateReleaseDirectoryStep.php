<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Shared;

use App\Modules\Deployments\Models\Release;
use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class CreateReleaseDirectoryStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'create-release-directory';
    }

    public function run(DeploymentContext $ctx): void
    {
        $owner = $this->webrootOwner($ctx);

        $this->runCommand($ctx, 'sudo mkdir -p '.$this->shellQuote($ctx->releasePath));
        $this->runCommand($ctx, 'sudo mkdir -p '.$this->shellQuote($ctx->sharedPath));
        $this->runCommand($ctx, sprintf(
            'sudo chown -R %s %s %s',
            $this->shellQuote($owner.':www-data'),
            $this->shellQuote($ctx->releasePath),
            $this->shellQuote($ctx->sharedPath),
        ));

        Release::query()->create([
            'site_id' => (string) $ctx->site->getKey(),
            'deployment_id' => (string) $ctx->deployment->getKey(),
            'organization_id' => (string) $ctx->site->organization_id,
            'path' => $ctx->releasePath,
            'commit_hash' => '',
            'is_active' => false,
            'created_at' => now(),
        ]);

        $ctx->deployment->forceFill(['release_path' => $ctx->releasePath])->save();
    }

    public function rollback(DeploymentContext $ctx): void
    {
        $this->runCommand($ctx, 'sudo rm -rf '.$this->shellQuote($ctx->releasePath));
        Release::query()
            ->where('deployment_id', (string) $ctx->deployment->getKey())
            ->where('path', $ctx->releasePath)
            ->delete();
    }

    private function webrootOwner(DeploymentContext $ctx): string
    {
        $sshUser = trim((string) $ctx->server->ssh_user);

        return $sshUser !== '' ? $sshUser : 'deploy';
    }
}
