<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Shared;

use App\Modules\Deployments\Models\Release;
use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class CloneRepositoryStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'clone-repository';
    }

    public function run(DeploymentContext $ctx): void
    {
        $branch = $ctx->deployment->branch ?? $ctx->site->deploy_branch;
        $repoUrl = $ctx->site->repository_url;

        if ($repoUrl === null || $repoUrl === '') {
            throw new \RuntimeException('Site repository_url is required for git deployments');
        }

        $this->runCommand($ctx, sprintf(
            'git clone --depth=1 --branch=%s %s %s',
            $this->shellQuote($branch),
            $this->shellQuote($repoUrl),
            $this->shellQuote($ctx->releasePath),
        ));

        $hashResult = $this->runCommand($ctx, 'git -C '.$this->shellQuote($ctx->releasePath).' rev-parse HEAD');
        $messageResult = $this->runCommand($ctx, 'git -C '.$this->shellQuote($ctx->releasePath).' log -1 --pretty=%s');

        $commitHash = trim($hashResult->stdout);
        $commitMessage = trim($messageResult->stdout);

        $ctx->deployment->forceFill([
            'commit_hash' => $commitHash,
            'commit_message' => $commitMessage,
        ])->save();

        Release::query()
            ->where('deployment_id', (string) $ctx->deployment->getKey())
            ->where('path', $ctx->releasePath)
            ->update(['commit_hash' => $commitHash]);
    }

    public function rollback(DeploymentContext $ctx): void
    {
        $this->runCommand($ctx, 'rm -rf '.$this->shellQuote($ctx->releasePath));
    }
}
