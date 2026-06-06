<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Events;

use App\Modules\Pipelines\Models\PipelineRun;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PipelineRejected implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PipelineRun $pipelineRun,
        public readonly string $rejectedByUserId,
        public readonly ?string $reason,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('organizations.'.$this->pipelineRun->organization_id),
        ];

        if ($this->pipelineRun->deployment_id !== null) {
            $channels[] = new PrivateChannel('deployment.'.$this->pipelineRun->deployment_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'pipeline.rejected';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'pipelineRunId' => (string) $this->pipelineRun->getKey(),
            'deploymentId' => $this->pipelineRun->deployment_id,
            'rejectedByUserId' => $this->rejectedByUserId,
            'reason' => $this->reason,
        ];
    }
}
