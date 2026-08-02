<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps;

use App\Packages\Execution\Contracts\DeploymentStepInterface;
use App\Packages\Execution\DeploymentContext;
use App\Packages\SSH\SSHResult;

abstract class BaseDeploymentStep implements DeploymentStepInterface
{
    public function rollback(DeploymentContext $ctx): void
    {
    }

    public function isSkippable(DeploymentContext $ctx): bool
    {
        return false;
    }

    protected function longCommandTimeoutSeconds(): int
    {
        return max(1, (int) config('helixdeploy.deployment_timeout_minutes', 30) * 60);
    }

    protected function runCommand(DeploymentContext $ctx, string $command, ?int $timeout = null): SSHResult
    {
        $previous = $ctx->executingStepName;
        $ctx->executingStepName = $this->name();

        try {
            return $ctx->run($command, $timeout);
        } finally {
            $ctx->executingStepName = $previous;
        }
    }

    protected function shellQuote(string $value): string
    {
        return escapeshellarg($value);
    }
}
