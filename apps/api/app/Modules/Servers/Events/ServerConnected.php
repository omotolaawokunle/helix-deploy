<?php

declare(strict_types=1);

namespace App\Modules\Servers\Events;

use App\Modules\Servers\Models\Server;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerConnected implements ShouldBroadcastNow
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
        return 'server.connected';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'serverId' => (string) $this->server->getKey(),
            'status' => $this->server->status?->value,
            'os' => $this->server->os,
            'phpVersion' => $this->server->php_version,
            'nodeVersion' => $this->server->node_version,
        ];
    }
}
