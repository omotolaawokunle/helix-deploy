<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Shared;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class CleanupOldReleasesStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'cleanup-old-releases';
    }

    public function run(DeploymentContext $ctx): void
    {
        $domain = $ctx->site->domain;
        $releasesGlob = '/var/www/'.$domain.'/releases/*/';

        $listResult = $this->runCommand($ctx, 'ls -1dt '.$releasesGlob);
        $paths = array_values(array_filter(
            array_map(static fn (string $line): string => rtrim(trim($line), '/'), explode("\n", trim($listResult->stdout))),
            static fn (string $path): bool => $path !== '',
        ));

        $activeResult = $this->runCommand($ctx, 'readlink -f '.$this->shellQuote($ctx->currentPath));
        $activePath = rtrim(trim($activeResult->stdout), '/');

        $retention = (int) config('helixdeploy.release_retention', 5);
        $toDelete = array_slice($paths, $retention);

        foreach ($toDelete as $path) {
            if ($path === $activePath) {
                continue;
            }

            $this->runCommand($ctx, 'rm -rf '.$this->shellQuote($path));
        }
    }
}
