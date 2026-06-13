<?php

declare(strict_types=1);

namespace App\Modules\Sites\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EnvVarsPulled implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $serverId,
        public readonly string $organizationId,
        public readonly string $siteId,
        public readonly string $strategy,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $deleted,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('server.'.$this->serverId.'.sites'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'env.vars.pulled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'serverId' => $this->serverId,
            'organizationId' => $this->organizationId,
            'siteId' => $this->siteId,
            'strategy' => $this->strategy,
            'created' => $this->created,
            'updated' => $this->updated,
            'deleted' => $this->deleted,
        ];
    }
}
