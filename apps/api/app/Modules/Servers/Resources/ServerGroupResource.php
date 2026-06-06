<?php

declare(strict_types=1);

namespace App\Modules\Servers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Servers\Models\ServerGroup
 */
class ServerGroupResource extends JsonResource
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
            'serversCount' => $this->whenCounted('servers'),
            'serverIds' => $this->whenLoaded('servers', fn () => $this->servers->map(
                static fn ($server): string => (string) $server->getKey(),
            )->all()),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
