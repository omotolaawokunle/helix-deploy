<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Events;

use App\Modules\BuildRunners\Models\BuildRunner;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BuildRunnerOffline implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public BuildRunner $runner,
        public ?string $reason = null,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('organizations.'.$this->runner->organization_id)];
    }

    public function broadcastAs(): string
    {
        return 'build_runner.offline';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'runnerId' => (string) $this->runner->getKey(),
            'status' => $this->runner->status?->value,
            'reason' => $this->reason,
        ];
    }
}
