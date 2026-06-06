<?php

declare(strict_types=1);

namespace App\Modules\Teams\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Teams\Models\Team
 */
class TeamResource extends JsonResource
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
            'memberCount' => (int) ($this->users_count ?? $this->users()->count()),
            'projectIds' => $this->whenLoaded(
                'projects',
                fn (): array => $this->projects->map(fn ($project): string => (string) $project->getKey())->all(),
                [],
            ),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
