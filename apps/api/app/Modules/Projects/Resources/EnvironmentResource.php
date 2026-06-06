<?php

declare(strict_types=1);

namespace App\Modules\Projects\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Projects\Models\Environment
 */
class EnvironmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'projectId' => (string) $this->project_id,
            'organizationId' => (string) $this->organization_id,
            'name' => $this->name,
            'label' => $this->label,
            'isProduction' => (bool) $this->is_production,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
