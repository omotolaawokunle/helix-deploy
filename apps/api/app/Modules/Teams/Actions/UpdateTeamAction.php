<?php

declare(strict_types=1);

namespace App\Modules\Teams\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Teams\DTOs\UpdateTeamDTO;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Str;

class UpdateTeamAction
{
    public function execute(Team $team, UpdateTeamDTO $dto): Team
    {
        $beforeState = [
            'name' => $team->name,
            'slug' => $team->slug,
        ];

        $slug = $this->generateUniqueSlug($team, $dto->name);

        $team->forceFill([
            'name' => $dto->name,
            'slug' => $slug,
        ])->save();

        AuditLog::record(
            operation: 'team.updated',
            resource: $team,
            beforeState: $beforeState,
            afterState: [
                'name' => $team->name,
                'slug' => $team->slug,
            ],
        );

        return $team->refresh();
    }

    private function generateUniqueSlug(Team $team, string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Team::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $team->organization_id)
                ->where('slug', $slug)
                ->whereKeyNot($team->getKey())
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
