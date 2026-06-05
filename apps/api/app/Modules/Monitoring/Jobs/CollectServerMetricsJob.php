<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Jobs;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Monitoring\Contracts\ServerMetricsCollectorInterface;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\SSHManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CollectServerMetricsJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('monitoring');
    }

    public function handle(
        SSHManager $sshManager,
        CredentialVault $vault,
        ServerMetricsCollectorInterface $collector,
    ): void {
        Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('status', ServerStatus::ACTIVE->value)
            ->chunkById(50, function ($servers) use ($sshManager, $vault, $collector): void {
                foreach ($servers as $server) {
                    if ($this->hasRunningDeployment((string) $server->getKey())) {
                        continue;
                    }

                    $this->collectForServer($server, $sshManager, $vault, $collector);
                }
            }, 'id');
    }

    private function collectForServer(
        Server $server,
        SSHManager $sshManager,
        CredentialVault $vault,
        ServerMetricsCollectorInterface $collector,
    ): void {
        try {
            $connection = $sshManager->connect($server, $vault)->connect();
            $metrics = $collector->collect($server, $connection);

            if ($metrics !== null) {
                $server->forceFill(['health_status' => $metrics])->save();
            }
        } catch (Throwable) {
            return;
        } finally {
            if (isset($connection)) {
                $connection->disconnect();
            }
        }
    }

    private function hasRunningDeployment(string $serverId): bool
    {
        return Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('status', DeploymentStatus::RUNNING->value)
            ->whereHas('site', fn ($query) => $query->where('server_id', $serverId))
            ->exists();
    }
}
