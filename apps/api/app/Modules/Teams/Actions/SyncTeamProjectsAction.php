<?php

declare(strict_types=1);

namespace App\Modules\Teams\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Team;
use Illuminate\Validation\ValidationException;

class SyncTeamProjectsAction
{
    /**
     * @param list<string> $projectIds
     */
    public function execute(Team $team, User $actor, array $projectIds): Team
    {
        $projectIds = array_values(array_unique($projectIds));

        if ($projectIds !== []) {
            $validCount = Project::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $team->organization_id)
                ->whereIn('id', $projectIds)
                ->count();

            if ($validCount !== count($projectIds)) {
                throw ValidationException::withMessages([
                    'projectIds' => ['One or more projects do not belong to this organization.'],
                ]);
            }
        }

        $beforeState = [
            'projectIds' => $team->projects()->pluck('projects.id')->map(
                static fn (mixed $id): string => (string) $id,
            )->all(),
        ];

        $team->projects()->sync($projectIds);

        AuditLog::record(
            operation: 'team.projects_synced',
            resource: $team,
            metadata: [
                'actor_id' => (string) $actor->getKey(),
            ],
            beforeState: $beforeState,
            afterState: [
                'projectIds' => $projectIds,
            ],
        );

        return $team->refresh()->load('projects');
    }
}
