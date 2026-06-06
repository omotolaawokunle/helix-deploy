<?php

declare(strict_types=1);

namespace App\Modules\Teams\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\DTOs\CreateTeamDTO;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Str;

class CreateTeamAction
{
    public function execute(Organization $organization, CreateTeamDTO $dto): Team
    {
        $team = Team::query()->create([
            'organization_id' => (string) $organization->getKey(),
            'name' => $dto->name,
            'slug' => $this->generateUniqueSlug($organization, $dto->name),
        ]);

        AuditLog::record(
            operation: 'team.created',
            resource: $team,
            afterState: [
                'name' => $team->name,
                'slug' => $team->slug,
            ],
        );

        return $team;
    }

    private function generateUniqueSlug(Organization $organization, string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Team::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $organization->getKey())
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
