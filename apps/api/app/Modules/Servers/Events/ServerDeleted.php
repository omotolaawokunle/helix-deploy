<?php

declare(strict_types=1);

namespace App\Modules\Servers\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerDeleted implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $serverId,
        public readonly string $organizationId,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('organizations.'.$this->organizationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'server.deleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'serverId' => $this->serverId,
            'organizationId' => $this->organizationId,
        ];
    }
}
