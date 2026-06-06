<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Go;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class ReplaceBinaryStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'replace-binary';
    }

    public function run(DeploymentContext $ctx): void
    {
        $target = $ctx->site->go_binary_path ?? '/usr/local/bin/'.$ctx->site->domain;
        $source = $ctx->releasePath.'/app';

        $this->runCommand($ctx, sprintf(
            'cp %s %s && chmod +x %s',
            $this->shellQuote($source),
            $this->shellQuote($target),
            $this->shellQuote($target),
        ));
    }
}
