<?php

declare(strict_types=1);

namespace App\Modules\Sites\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SiteLogsReady implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $serverId,
        public readonly string $organizationId,
        public readonly string $siteId,
        public readonly string $logType,
        public readonly int $linesRequested,
        public readonly string $status,
        public readonly ?string $message = null,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('server.'.$this->serverId.'.logs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'site.logs.ready';
    }

    /**
     * Notification-only payload. Log lines are served from cache via the HTTP API.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'serverId' => $this->serverId,
            'organizationId' => $this->organizationId,
            'siteId' => $this->siteId,
            'logType' => $this->logType,
            'linesRequested' => $this->linesRequested,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
