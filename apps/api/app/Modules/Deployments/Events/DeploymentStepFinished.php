<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Events;

use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Models\Deployment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentStepFinished implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Deployment $deployment,
        public readonly string $stepName,
        public readonly int $order,
        public readonly DeploymentStepStatus $status,
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
        return 'deployment.step.finished';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'deploymentId' => (string) $this->deployment->getKey(),
            'stepName' => $this->stepName,
            'order' => $this->order,
            'status' => $this->status->value,
        ];
    }
}
