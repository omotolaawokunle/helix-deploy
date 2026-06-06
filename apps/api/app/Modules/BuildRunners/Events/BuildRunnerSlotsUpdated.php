<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Events;

use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Support\BuildRunnerSlotBroadcast;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BuildRunnerSlotsUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public BuildRunner $runner)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('organizations.'.$this->runner->organization_id)];
    }

    public function broadcastAs(): string
    {
        return 'build_runner.slots_updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return BuildRunnerSlotBroadcast::payload(
            $this->runner,
            app(RunnerSlotManager::class),
        );
    }
}
