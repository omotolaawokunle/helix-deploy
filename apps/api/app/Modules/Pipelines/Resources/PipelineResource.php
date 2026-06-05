<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pipelines\Models\Pipeline
 */
class PipelineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'organizationId' => (string) $this->organization_id,
            'projectId' => $this->project_id !== null ? (string) $this->project_id : null,
            'name' => $this->name,
            'description' => $this->description,
            'steps' => PipelineStepResource::collection($this->whenLoaded('steps')),
            'sitesCount' => $this->whenCounted('sites'),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
