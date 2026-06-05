<?php

declare(strict_types=1);

namespace App\Modules\Projects\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Contracts\TeamProjectVisibilityServiceInterface;
use App\Modules\Teams\Enums\TeamRole;

class ProjectPolicy
{
    public function viewAny(User $user, Organization $org): bool
    {
        return $this->roleInOrganization($user, $org) !== null;
    }

    public function view(User $user, Project $project): bool
    {
        if ($this->roleInOrganization($user, $project->organization) === null) {
            return false;
        }

        return app(TeamProjectVisibilityServiceInterface::class)->canAccessProject($user, $project);
    }

    public function create(User $user, Organization $org): bool
    {
        return in_array($this->roleInOrganization($user, $org), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function update(User $user, Project $project): bool
    {
        return in_array($this->roleInOrganization($user, $project->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function delete(User $user, Project $project): bool
    {
        return in_array($this->roleInOrganization($user, $project->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    private function roleInOrganization(User $user, Organization $org): ?TeamRole
    {
        return $user->roleInOrganization($org);
    }
}
