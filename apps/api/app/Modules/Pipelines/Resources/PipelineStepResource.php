<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pipelines\Models\PipelineStep
 */
class PipelineStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'pipelineId' => (string) $this->pipeline_id,
            'name' => $this->name,
            'type' => $this->type->value,
            'order' => $this->order,
            'config' => $this->config ?? [],
            'requiresApproval' => $this->requires_approval,
            'approverRole' => $this->approver_role?->value,
            'retryAttempts' => $this->retry_attempts,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
