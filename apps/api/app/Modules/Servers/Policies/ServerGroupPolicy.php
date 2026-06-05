<?php

declare(strict_types=1);

namespace App\Modules\Servers\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\ServerGroup;
use App\Modules\Teams\Enums\TeamRole;

class ServerGroupPolicy
{
    public function viewAny(User $user, Organization $org): bool
    {
        return $this->roleInOrganization($user, $org) !== null;
    }

    public function view(User $user, ServerGroup $group): bool
    {
        return $this->roleInOrganization($user, $group->organization) !== null;
    }

    public function create(User $user, Organization $org): bool
    {
        return in_array($this->roleInOrganization($user, $org), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function update(User $user, ServerGroup $group): bool
    {
        return in_array(
            $this->roleInOrganization($user, $group->organization),
            [TeamRole::OWNER, TeamRole::ADMIN],
            true,
        );
    }

    public function delete(User $user, ServerGroup $group): bool
    {
        return in_array(
            $this->roleInOrganization($user, $group->organization),
            [TeamRole::OWNER, TeamRole::ADMIN],
            true,
        );
    }

    public function syncServers(User $user, ServerGroup $group): bool
    {
        return $this->update($user, $group);
    }

    private function roleInOrganization(User $user, Organization $org): ?TeamRole
    {
        return $user->roleInOrganization($org);
    }
}
