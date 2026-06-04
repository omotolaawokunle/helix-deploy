<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Events;

use App\Modules\Deployments\Models\Deployment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentLogLine implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Deployment $deployment,
        public readonly string $line,
        public readonly ?string $stepId = null,
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
        return 'deployment.log_line';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->streamPayload();
    }

    /**
     * @return array<string, string|null>
     */
    public function streamPayload(): array
    {
        return [
            'stepId' => $this->stepId,
            'line' => $this->line,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
