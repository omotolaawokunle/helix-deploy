<?php

declare(strict_types=1);

namespace App\Modules\Servers\Events;

use App\Modules\Servers\Models\Server;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerInventoryDiscovered implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array{
     *     installedServices: array<string, array<string, mixed>>,
     *     sitesCreated: int,
     *     sitesUpdated: int,
     *     discoveredSiteCount: int
     * } $inventory
     */
    public function __construct(
        public Server $server,
        public array $inventory,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('organizations.'.$this->server->organization_id)];
    }

    public function broadcastAs(): string
    {
        return 'server.inventory_discovered';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'serverId' => (string) $this->server->getKey(),
            'installedServices' => $this->inventory['installedServices'],
            'sitesCreated' => $this->inventory['sitesCreated'],
            'sitesUpdated' => $this->inventory['sitesUpdated'],
            'discoveredSiteCount' => $this->inventory['discoveredSiteCount'],
        ];
    }
}
