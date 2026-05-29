<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Organizations\Models\Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => (string) $this->name,
            'slug' => (string) $this->slug,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
