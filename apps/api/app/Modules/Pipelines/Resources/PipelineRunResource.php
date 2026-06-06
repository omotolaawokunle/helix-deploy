<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pipelines\Models\PipelineRun
 */
class PipelineRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'organizationId' => (string) $this->organization_id,
            'pipelineId' => (string) $this->pipeline_id,
            'siteId' => (string) $this->site_id,
            'deploymentId' => $this->deployment_id !== null ? (string) $this->deployment_id : null,
            'status' => $this->status->value,
            'currentStepOrder' => $this->current_step_order,
            'steps' => PipelineRunStepResource::collection($this->whenLoaded('steps')),
            'startedAt' => $this->started_at?->toIso8601String(),
            'finishedAt' => $this->finished_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
