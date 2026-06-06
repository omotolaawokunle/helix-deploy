<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Shared;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;
use App\Packages\Execution\Steps\BaseDeploymentStep;
use App\Packages\Execution\Support\DiskSpaceParser;
use App\Packages\SSH\SSHResult;

final class VerifyConnectionStep extends BaseDeploymentStep
{
    private const MINIMUM_BYTES = 200 * 1024 * 1024;

    private const WARNING_BYTES = 1024 * 1024 * 1024;

    public function name(): string
    {
        return 'verify-connection';
    }

    public function run(DeploymentContext $ctx): void
    {
        $this->runCommand($ctx, 'echo "_ok_"');

        $dfResult = $this->runCommand($ctx, "df -h / | awk 'NR==2 {print \$4}'");
        $available = DiskSpaceParser::parseAvailableBytes(trim($dfResult->stdout));

        if ($available === null) {
            throw new DeploymentStepFailedException(
                '[verify-connection] unable to parse available disk space',
                new SSHResult('df', 1, '', 'parse error', 0.0),
                $this->name(),
            );
        }

        if ($available < self::MINIMUM_BYTES) {
            throw new DeploymentStepFailedException(
                '[verify-connection] insufficient disk space',
                $dfResult,
                $this->name(),
            );
        }

        if ($available < self::WARNING_BYTES) {
            $ctx->log('WARNING: available disk space is below 1GB');
        }
    }
}
