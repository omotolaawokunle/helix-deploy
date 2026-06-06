<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Shared;

use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Models\Release;
use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class ActivateReleaseStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'activate-release';
    }

    public function run(DeploymentContext $ctx): void
    {
        $this->runCommand($ctx, sprintf(
            'ln -sfn %s %s',
            $this->shellQuote($ctx->releasePath),
            $this->shellQuote($ctx->currentPath),
        ));

        $verify = $this->runCommand($ctx, 'readlink -f '.$this->shellQuote($ctx->currentPath));
        $resolved = rtrim(trim($verify->stdout), '/');
        $expected = rtrim($ctx->releasePath, '/');

        if ($resolved !== $expected) {
            throw new DeploymentStepFailedException(
                sprintf(
                    '[activate-release] symlink verification failed: expected %s, got %s',
                    $expected,
                    $resolved,
                ),
                $verify,
                $this->name(),
            );
        }

        Release::query()
            ->where('site_id', (string) $ctx->site->getKey())
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $releaseDeploymentId = $ctx->deployment->type === DeploymentType::ROLLBACK
            && $ctx->deployment->rollback_target_id !== null
            ? (string) $ctx->deployment->rollback_target_id
            : (string) $ctx->deployment->getKey();

        Release::query()
            ->where('deployment_id', $releaseDeploymentId)
            ->where('path', $ctx->releasePath)
            ->update(['is_active' => true]);
    }
}
