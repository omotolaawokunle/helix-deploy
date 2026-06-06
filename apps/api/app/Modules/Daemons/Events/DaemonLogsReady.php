<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DaemonLogsReady implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly string $serverId,
        public readonly string $organizationId,
        public readonly string $daemonId,
        public readonly string $status,
        public readonly array $lines = [],
        public readonly ?string $message = null,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('server.'.$this->serverId.'.daemons'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'daemon.logs.ready';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'serverId' => $this->serverId,
            'organizationId' => $this->organizationId,
            'daemonId' => $this->daemonId,
            'status' => $this->status,
            'lines' => $this->lines,
            'message' => $this->message,
        ];
    }
}
