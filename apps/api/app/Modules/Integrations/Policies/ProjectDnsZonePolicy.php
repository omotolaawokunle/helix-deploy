<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Policies;

use App\Models\User;
use App\Modules\Integrations\Models\ProjectDnsZone;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Services\TeamProjectVisibilityService;

final class ProjectDnsZonePolicy
{
    public function __construct(
        private readonly TeamProjectVisibilityService $visibilityService,
    ) {
    }

    public function viewAny(User $user, Project $project): bool
    {
        return $this->canAccessProject($user, $project);
    }

    public function manage(User $user, Project $project): bool
    {
        $organization = $project->organization;

        if ($organization === null) {
            return false;
        }

        return in_array($this->roleInOrganization($user, $organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function delete(User $user, ProjectDnsZone $projectDnsZone): bool
    {
        $project = $projectDnsZone->project;

        return $project !== null && $this->manage($user, $project);
    }

    private function canAccessProject(User $user, Project $project): bool
    {
        $organization = $project->organization;

        if ($organization === null) {
            return false;
        }

        if ($this->roleInOrganization($user, $organization) === null) {
            return false;
        }

        $visibleProjectIds = $this->visibilityService->visibleProjectIds($user, $organization);

        if ($visibleProjectIds === null) {
            return true;
        }

        return in_array((string) $project->getKey(), $visibleProjectIds, true);
    }

    private function roleInOrganization(User $user, Organization $org): ?TeamRole
    {
        return $user->roleInOrganization($org);
    }
}
