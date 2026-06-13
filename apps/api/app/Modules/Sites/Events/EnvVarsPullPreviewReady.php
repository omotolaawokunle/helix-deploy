<?php

declare(strict_types=1);

namespace App\Modules\Sites\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EnvVarsPullPreviewReady implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>|null  $diff
     */
    public function __construct(
        public readonly string $serverId,
        public readonly string $organizationId,
        public readonly string $siteId,
        public readonly string $status,
        public readonly ?array $diff = null,
        public readonly ?string $message = null,
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
        return 'env.vars.pull.preview.ready';
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
            'status' => $this->status,
            'diff' => $this->diff,
            'message' => $this->message,
        ];
    }
}
