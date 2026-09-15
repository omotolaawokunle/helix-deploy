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
        $this->runCommand(
            $ctx,
            'cd '.$this->shellQuote($ctx->releasePath).' && npm ci && npm run build',
            self::TIMEOUT_SECONDS,
        );
    }

    public function isSkippable(DeploymentContext $ctx): bool
    {
        if (! $ctx->site->build_assets) {
            return true;
        }

        $packageJson = $ctx->releasePath.'/package.json';
        $check = $ctx->ssh->run('test -f '.$this->shellQuote($packageJson));

        return $check->failed();
    }
}
