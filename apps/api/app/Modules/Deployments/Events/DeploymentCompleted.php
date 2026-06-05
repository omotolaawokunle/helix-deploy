<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Events;

use App\Modules\Deployments\Models\Deployment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentCompleted implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Deployment $deployment,
        public readonly ?string $releaseId = null,
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
        return 'deployment.completed';
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
            'duration' => $this->deployment->duration(),
            'releaseId' => $this->releaseId,
            'commitHash' => $this->deployment->commit_hash,
            'finishedAt' => $this->deployment->finished_at?->toIso8601String(),
        ];
    }
}
