<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Resources;

use App\Modules\Deployments\Enums\DeploymentStepStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Deployments\Models\DeploymentStep
 */
class DeploymentStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'deploymentId' => $this->deployment_id,
            'name' => $this->name,
            'status' => $this->status instanceof DeploymentStepStatus
                ? $this->status->value
                : $this->status,
            'order' => $this->order,
            'exitCode' => $this->exit_code,
            'startedAt' => $this->started_at?->toIso8601String(),
            'finishedAt' => $this->finished_at?->toIso8601String(),
        ];
    }
}
