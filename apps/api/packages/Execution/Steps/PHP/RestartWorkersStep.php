<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\PHP;

use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Models\Deployment;
use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;
use App\Packages\Execution\Support\PhpBinary;
use Illuminate\Support\Collection;

final class RestartWorkersStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'restart-workers';
    }

    public function isSkippable(DeploymentContext $ctx): bool
    {
        if ($this->discoveredWorkers($ctx)->isNotEmpty()) {
            return false;
        }

        return ! Deployment::query()
            ->where('site_id', (string) $ctx->site->getKey())
            ->whereKeyNot($ctx->deployment->getKey())
            ->where('status', DeploymentStatus::SUCCESS)
            ->exists();
    }

    public function run(DeploymentContext $ctx): void
    {
        $release = $this->shellQuote($ctx->releasePath);
        $php = PhpBinary::forSite($ctx->site);

        if ($this->hasHorizon($ctx)) {
            $this->runCommand($ctx, 'cd '.$release.' && '.$php.' artisan horizon:terminate');
        } else {
            $this->runCommand($ctx, 'cd '.$release.' && '.$php.' artisan queue:restart');
        }

        foreach ($this->discoveredWorkers($ctx) as $worker) {
            $this->runCommand($ctx, $this->supervisorRestartCommand($worker->name));
        }
    }

    private function hasHorizon(DeploymentContext $ctx): bool
    {
        // Prefer the installed package over config/horizon.php — config can remain
        // after composer --no-dev strips laravel/horizon from vendor.
        $horizon = $ctx->ssh->run(
            'test -d '.$this->shellQuote($ctx->releasePath.'/vendor/laravel/horizon'),
        );

        return $horizon->exitCode === 0;
    }

    /**
     * @return Collection<int, SupervisorProcess>
     */
    private function discoveredWorkers(DeploymentContext $ctx): Collection
    {
        $currentPath = rtrim($ctx->currentPath, '/');
        $siteId = (string) $ctx->site->getKey();

        return SupervisorProcess::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $ctx->server->getKey())
            ->where('organization_id', (string) $ctx->site->organization_id)
            ->orderBy('name')
            ->get()
            ->filter(function (SupervisorProcess $daemon) use ($currentPath, $siteId): bool {
                if (preg_match('/artisan\s+(horizon|queue:work|reverb:start)(?:\s|$)/', (string) $daemon->command) !== 1) {
                    return false;
                }

                if ((string) $daemon->site_id === $siteId) {
                    return true;
                }

                $directory = rtrim((string) $daemon->directory, '/');

                return $directory === $currentPath
                    || str_contains((string) $daemon->command, $currentPath);
            })
            ->values();
    }

    private function supervisorRestartCommand(string $name): string
    {
        $group = escapeshellarg($name.':*');
        $process = escapeshellarg($name);

        return sprintf(
            'sudo supervisorctl restart %s || sudo supervisorctl restart %s',
            $group,
            $process,
        );
    }
}
