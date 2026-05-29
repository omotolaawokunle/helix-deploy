<?php

declare(strict_types=1);

namespace App\Modules\Servers\Events;

use App\Modules\Servers\Models\Server;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerConnectionFailed implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Server $server,
        public ?string $reason = null,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('organizations.'.$this->server->organization_id)];
    }

    public function broadcastAs(): string
    {
        return 'server.connection_failed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'serverId' => (string) $this->server->getKey(),
            'status' => $this->server->status?->value,
            'reason' => $this->reason,
        ];
    }
}
