<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Provisioning\Models\ProvisioningTemplate
 */
class ProvisioningTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'organizationId' => $this->organization_id !== null ? (string) $this->organization_id : null,
            'name' => $this->name,
            'description' => $this->description,
            'services' => $this->services ?? [],
            'options' => $this->options ?? [],
            'isSystem' => (bool) $this->is_system,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
