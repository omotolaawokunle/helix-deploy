<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Shared;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;
use App\Packages\Execution\Steps\BaseDeploymentStep;
use App\Packages\SSH\SSHResult;

final class VerifyReleaseExistsStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'verify-release-exists';
    }

    public function run(DeploymentContext $ctx): void
    {
        $path = $this->shellQuote($ctx->releasePath);
        $result = $this->runCommand($ctx, "test -d {$path} && echo exists || echo missing");
        $output = trim($result->stdout);

        if ($output !== 'exists') {
            throw new DeploymentStepFailedException(
                sprintf('[verify-release-exists] release directory not found: %s', $ctx->releasePath),
                new SSHResult('test -d', 1, $output, '', 0.0),
                $this->name(),
            );
        }
    }
}
