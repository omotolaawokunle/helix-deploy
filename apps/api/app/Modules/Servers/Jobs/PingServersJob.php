<?php

declare(strict_types=1);

namespace App\Modules\Servers\Jobs;

use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Servers\Events\ServerHealthChanged;
use App\Modules\Servers\Models\Server;
use App\Modules\Credentials\CredentialVault;
use App\Packages\SSH\SSHManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;
use Throwable;

class PingServersJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('monitoring');
    }

    public function handle(SSHManager $sshManager, CredentialVault $vault): void
    {
        Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereIn('status', [
                ServerStatus::ACTIVE->value,
                ServerStatus::DISCONNECTED->value,
            ])
            ->with('environment')
            ->chunkById(100, function ($servers) use ($sshManager, $vault): void {
                foreach ($servers as $server) {
                    if ($this->hasRunningDeployment((string) $server->getKey())) {
                        continue;
                    }

                    $this->pingServer($server, $sshManager, $vault);
                }
            }, 'id');
    }

    private function pingServer(Server $server, SSHManager $sshManager, CredentialVault $vault): void
    {
        $failureKey = $this->failureKey((string) $server->getKey());

        try {
            $connection = $sshManager->connect($server, $vault)->connect();
            $connection->run('echo "_ping_"', timeout: 10)->throw();

            $previousStatus = $server->status?->value ?? ServerStatus::ACTIVE->value;
            Redis::del($failureKey);

            if ($server->status !== ServerStatus::ACTIVE) {
                $server->forceFill(['status' => ServerStatus::ACTIVE->value])->save();
                event(new ServerHealthChanged($server->refresh(), $previousStatus));
            }
        } catch (Throwable) {
            $failures = (int) Redis::incr($failureKey);

            if ($failures >= 3 && $server->status !== ServerStatus::DISCONNECTED) {
                $previousStatus = $server->status?->value ?? ServerStatus::ACTIVE->value;
                $server->forceFill(['status' => ServerStatus::DISCONNECTED->value])->save();
                event(new ServerHealthChanged($server->refresh(), $previousStatus));
            }
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

    private function failureKey(string $serverId): string
    {
        return "ping_failures:{$serverId}";
    }
}
