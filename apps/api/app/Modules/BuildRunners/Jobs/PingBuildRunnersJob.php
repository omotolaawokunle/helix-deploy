<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Jobs;

use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Events\BuildRunnerOffline;
use App\Modules\BuildRunners\Events\BuildRunnerOnline;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Models\Deployment;
use App\Packages\SSH\BuildRunnerSSHManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;
use Throwable;

class PingBuildRunnersJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('monitoring');
    }

    public function handle(BuildRunnerSSHManager $sshManager, CredentialVault $vault): void
    {
        BuildRunner::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereIn('status', [
                BuildRunnerStatus::ONLINE->value,
                BuildRunnerStatus::OFFLINE->value,
            ])
            ->whereNotNull('credential_id')
            ->chunkById(100, function ($runners) use ($sshManager, $vault): void {
                foreach ($runners as $runner) {
                    if ($this->hasActiveBuild((string) $runner->getKey())) {
                        continue;
                    }

                    $this->pingRunner($runner, $sshManager, $vault);
                }
            }, 'id');
    }

    private function pingRunner(BuildRunner $runner, BuildRunnerSSHManager $sshManager, CredentialVault $vault): void
    {
        $failureKey = $this->failureKey((string) $runner->getKey());

        try {
            $connection = $sshManager->connect($runner, $vault)->connect();
            $connection->run('echo "_ping_"', timeout: 10)->throw();

            Redis::del($failureKey);

            if ($runner->status !== BuildRunnerStatus::ONLINE) {
                $runner->forceFill(['status' => BuildRunnerStatus::ONLINE->value])->save();
                event(new BuildRunnerOnline($runner->refresh()));
            }
        } catch (Throwable) {
            $failures = (int) Redis::incr($failureKey);

            if ($failures >= 3 && $runner->status !== BuildRunnerStatus::OFFLINE) {
                $runner->forceFill(['status' => BuildRunnerStatus::OFFLINE->value])->save();
                event(new BuildRunnerOffline($runner->refresh(), 'Ping failures exceeded threshold.'));
            }
        } finally {
            if (isset($connection)) {
                $connection->disconnect();
            }
        }
    }

    private function hasActiveBuild(string $runnerId): bool
    {
        return Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('build_runner_id', $runnerId)
            ->where('status', DeploymentStatus::BUILDING->value)
            ->exists();
    }

    private function failureKey(string $runnerId): string
    {
        return "runner_ping_failures:{$runnerId}";
    }
}
