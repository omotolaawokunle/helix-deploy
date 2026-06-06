<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Events;

use App\Modules\Servers\Models\Server;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerMetricsUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Server $server)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('organizations.'.$this->server->organization_id)];
    }

    public function broadcastAs(): string
    {
        return 'server.metrics_updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        /** @var array<string, mixed> $health */
        $health = $this->server->health_status ?? [];

        return [
            'serverId' => (string) $this->server->getKey(),
            'cpuPercent' => $health['cpuPercent'] ?? null,
            'memoryUsedPercent' => $health['memoryUsedPercent'] ?? null,
            'diskUsedPercent' => $health['diskUsedPercent'] ?? null,
            'lastCheckedAt' => $health['lastCheckedAt'] ?? null,
        ];
    }
}
