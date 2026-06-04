<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Go;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class DownloadBinaryStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'download-binary';
    }

    public function run(DeploymentContext $ctx): void
    {
        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($ctx->releasePath).' && go build -o '.$this->shellQuote('app'),
        );
    }
}
