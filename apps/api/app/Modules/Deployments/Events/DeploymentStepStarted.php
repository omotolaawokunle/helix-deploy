<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Events;

use App\Modules\Deployments\Models\Deployment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentStepStarted implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Deployment $deployment,
        public readonly string $stepName,
        public readonly int $order,
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
        return 'deployment.step.started';
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
        ];
    }
}
