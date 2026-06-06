<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Events;

use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\DeploymentStep;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentStepUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Deployment $deployment,
        public readonly DeploymentStep $step,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('deployment.'.$this->deployment->getKey())];
    }

    public function broadcastAs(): string
    {
        return 'deployment.step.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->streamPayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function streamPayload(): array
    {
        return [
            'stepId' => (string) $this->step->getKey(),
            'name' => $this->step->name,
            'order' => $this->step->order,
            'phase' => $this->step->phase?->value ?? 'deploy',
            'status' => $this->step->status->value,
            'duration' => $this->stepDuration(),
        ];
    }

    public function sseEventName(): string
    {
        return match ($this->step->status) {
            DeploymentStepStatus::RUNNING => 'step.started',
            DeploymentStepStatus::SUCCESS,
            DeploymentStepStatus::FAILED,
            DeploymentStepStatus::SKIPPED => 'step.completed',
            default => 'step.updated',
        };
    }

    private function stepDuration(): ?float
    {
        if ($this->step->started_at === null || $this->step->finished_at === null) {
            return null;
        }

        return (float) $this->step->finished_at->floatDiffInSeconds($this->step->started_at);
    }
}
