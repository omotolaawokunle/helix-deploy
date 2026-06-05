<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Static;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class BuildStaticAssetsStep extends BaseDeploymentStep
{
    private const TIMEOUT_SECONDS = 900;

    public function name(): string
    {
        return 'build-static-assets';
    }

    public function run(DeploymentContext $ctx): void
    {
        $packageJson = $ctx->releasePath.'/package.json';
        $check = $ctx->ssh->run('test -f '.$this->shellQuote($packageJson));

        if ($check->failed()) {
            $ctx->log('No package.json found — skipping static asset build.');

            return;
        }

        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($ctx->releasePath).' && npm ci && npm run build',
            self::TIMEOUT_SECONDS,
        );
    }
}
