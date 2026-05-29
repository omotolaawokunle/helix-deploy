<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Events;

use App\Modules\Servers\Models\Server;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProvisioningCompleted implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Server $server,
        public readonly string $runId,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('server.'.$this->server->getKey().'.provisioning')];
    }

    public function broadcastAs(): string
    {
        return 'provisioning.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'serverId' => (string) $this->server->getKey(),
            'runId' => $this->runId,
            'installedServices' => (array) $this->server->installed_services,
        ];
    }
}
