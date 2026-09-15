<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps;

use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Contracts\BuildStepInterface;
use App\Packages\SSH\SSHResult;

abstract class BaseBuildStep implements BuildStepInterface
{
    public function rollback(BuildContext $ctx): void {}

    public function isSkippable(BuildContext $ctx): bool
    {
        return false;
    }

    protected function runCommand(BuildContext $ctx, string $command, ?int $timeout = null): SSHResult
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

    protected function workPath(BuildContext $ctx): string
    {
        return rtrim($ctx->buildPath, '/');
    }

    /**
     * True when the build workspace already contains a Vite production build.
     */
    protected function hasPrebuiltViteManifest(BuildContext $ctx, string $appPath): bool
    {
        $manifest = rtrim($appPath, '/').'/public/build/manifest.json';
        $result = $ctx->ssh->run('test -f '.$this->shellQuote($manifest));

        return $result->exitCode === 0;
    }
}
