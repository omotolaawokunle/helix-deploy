<?php

declare(strict_types=1);

namespace App\Modules\Teams\Services;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Contracts\TeamProjectVisibilityServiceInterface;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Models\Team;

class TeamProjectVisibilityService implements TeamProjectVisibilityServiceInterface
{
    public function visibleProjectIds(User $user, Organization $org): ?array
    {
        $orgRole = $user->roleInOrganization($org);

        if (in_array($orgRole, [TeamRole::OWNER, TeamRole::ADMIN], true)) {
            return null;
        }

        $teams = Team::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $org->getKey())
            ->whereHas('users', function ($query) use ($user): void {
                $query->whereKey($user->getKey());
            })
            ->with('projects:id')
            ->get();

        if ($teams->isEmpty()) {
            return null;
        }

        $projectIds = [];
        $hasUnrestrictedTeam = false;

        foreach ($teams as $team) {
            if ($team->projects->isEmpty()) {
                $hasUnrestrictedTeam = true;

                break;
            }

            foreach ($team->projects as $project) {
                $projectIds[] = (string) $project->getKey();
            }
        }

        if ($hasUnrestrictedTeam) {
            return null;
        }

        return array_values(array_unique($projectIds));
    }

    public function canAccessProject(User $user, Project $project): bool
    {
        $organization = $project->organization;

        if ($organization === null) {
            return false;
        }

        $visibleProjectIds = $this->visibleProjectIds($user, $organization);

        if ($visibleProjectIds === null) {
            return $user->roleInOrganization($organization) !== null;
        }

        return in_array((string) $project->getKey(), $visibleProjectIds, true);
    }
}
