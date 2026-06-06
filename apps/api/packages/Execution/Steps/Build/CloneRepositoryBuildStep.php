<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Build;

use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Steps\BaseBuildStep;

final class CloneRepositoryBuildStep extends BaseBuildStep
{
    public function name(): string
    {
        return 'clone-repository';
    }

    public function run(BuildContext $ctx): void
    {
        $branch = $ctx->deployment->branch ?? $ctx->site->deploy_branch;
        $repoUrl = $ctx->repositoryCloneUrl ?? $ctx->site->repository_url;

        if ($repoUrl === null || $repoUrl === '') {
            throw new \RuntimeException('Site repository_url is required for git deployments');
        }

        $this->runCommand($ctx, sprintf(
            'git clone --depth=1 --branch=%s %s %s',
            $this->shellQuote($branch),
            $this->shellQuote($repoUrl),
            $this->shellQuote($this->workPath($ctx)),
        ));

        $hashResult = $this->runCommand($ctx, 'git -C '.$this->shellQuote($this->workPath($ctx)).' rev-parse HEAD');
        $messageResult = $this->runCommand($ctx, 'git -C '.$this->shellQuote($this->workPath($ctx)).' log -1 --pretty=%s');

        $ctx->deployment->forceFill([
            'commit_hash' => trim($hashResult->stdout),
            'commit_message' => trim($messageResult->stdout),
        ])->save();
    }
}
