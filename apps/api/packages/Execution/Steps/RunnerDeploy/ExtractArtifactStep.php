<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\RunnerDeploy;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class ExtractArtifactStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'extract-artifact';
    }

    public function run(DeploymentContext $ctx): void
    {
        if ($ctx->artifact === null) {
            throw new \RuntimeException('Build artifact is required to extract on the target server.');
        }

        $artifactPath = $ctx->artifact->storage_path;
        $owner = $this->webrootOwner($ctx);

        $this->runCommand($ctx, 'sudo mkdir -p '.$this->shellQuote($ctx->releasePath));
        $this->runCommand($ctx, sprintf(
            'sudo chown -R %s %s',
            $this->shellQuote($owner.':www-data'),
            $this->shellQuote($ctx->releasePath),
        ));
        $this->runCommand($ctx, sprintf(
            'tar -xzf %s -C %s',
            $this->shellQuote($artifactPath),
            $this->shellQuote($ctx->releasePath),
        ));
    }

    public function rollback(DeploymentContext $ctx): void
    {
        $this->runCommand($ctx, 'sudo rm -rf '.$this->shellQuote($ctx->releasePath));
    }

    private function webrootOwner(DeploymentContext $ctx): string
    {
        $sshUser = trim((string) $ctx->server->ssh_user);

        return $sshUser !== '' ? $sshUser : 'deploy';
    }
}
