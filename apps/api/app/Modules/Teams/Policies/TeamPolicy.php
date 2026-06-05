<?php

declare(strict_types=1);

namespace App\Modules\Teams\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Models\Team;

class TeamPolicy
{
    public function viewAny(User $user, Organization $org): bool
    {
        return $this->roleInOrganization($user, $org) !== null;
    }

    public function view(User $user, Team $team): bool
    {
        $orgRole = $this->roleInOrganization($user, $team->organization);

        if ($orgRole === null) {
            return false;
        }

        if (in_array($orgRole, [TeamRole::OWNER, TeamRole::ADMIN], true)) {
            return true;
        }

        return $team->users()
            ->whereKey($user->getKey())
            ->exists();
    }

    public function create(User $user, Organization $org): bool
    {
        return in_array($this->roleInOrganization($user, $org), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function update(User $user, Team $team): bool
    {
        return in_array($this->roleInOrganization($user, $team->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function delete(User $user, Team $team): bool
    {
        return in_array($this->roleInOrganization($user, $team->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return in_array($this->roleInOrganization($user, $team->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function syncProjects(User $user, Team $team): bool
    {
        return in_array($this->roleInOrganization($user, $team->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    private function roleInOrganization(User $user, Organization $org): ?TeamRole
    {
        return $user->roleInOrganization($org);
    }
}
