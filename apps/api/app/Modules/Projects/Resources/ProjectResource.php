<?php

declare(strict_types=1);

namespace App\Modules\Projects\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Projects\Models\Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'organizationId' => (string) $this->organization_id,
            'name' => $this->name,
            'description' => $this->description,
            'environmentsCount' => $this->whenCounted('environments'),
            'serversCount' => $this->whenCounted('servers'),
            'sitesCount' => $this->whenCounted('sites'),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
