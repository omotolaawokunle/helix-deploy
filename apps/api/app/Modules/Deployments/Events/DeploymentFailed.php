<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Events;

use App\Modules\Deployments\Models\Deployment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentFailed implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Deployment $deployment,
        public readonly string $message,
        public readonly ?string $failedStepName = null,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('deployment.'.$this->deployment->getKey()),
            new PrivateChannel('organizations.'.$this->deployment->organization_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'deployment.failed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'deploymentId' => (string) $this->deployment->getKey(),
            'siteId' => $this->deployment->site_id,
            'organizationId' => $this->deployment->organization_id,
            'status' => $this->deployment->status->value,
            'message' => $this->message,
            'failedStepName' => $this->failedStepName,
            'finishedAt' => $this->deployment->finished_at?->toIso8601String(),
        ];
    }
}
