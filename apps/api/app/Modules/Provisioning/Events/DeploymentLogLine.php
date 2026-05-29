<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Events;

use App\Modules\Servers\Models\Server;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentLogLine implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Server $server,
        public readonly string $runId,
        public readonly string $line,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('server.'.$this->server->getKey().'.provisioning')];
    }

    public function broadcastAs(): string
    {
        return 'provisioning.log_line';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'serverId' => (string) $this->server->getKey(),
            'runId' => $this->runId,
            'line' => $this->line,
        ];
    }
}
