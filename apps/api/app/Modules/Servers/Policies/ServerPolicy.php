<?php

declare(strict_types=1);

namespace App\Modules\Servers\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Contracts\TeamProjectVisibilityServiceInterface;
use App\Modules\Teams\Enums\TeamRole;

class ServerPolicy
{
    public function viewAny(User $user, Organization $org): bool
    {
        return $this->roleInOrganization($user, $org) !== null;
    }

    public function view(User $user, Server $server): bool
    {
        if ($this->roleInOrganization($user, $server->organization) === null) {
            return false;
        }

        $project = $server->project;

        if ($project === null) {
            return app(TeamProjectVisibilityServiceInterface::class)
                ->visibleProjectIds($user, $server->organization) === null;
        }

        return app(TeamProjectVisibilityServiceInterface::class)->canAccessProject($user, $project);
    }

    public function create(User $user, Organization $org): bool
    {
        return in_array($this->roleInOrganization($user, $org), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function update(User $user, Server $server): bool
    {
        return in_array($this->roleInOrganization($user, $server->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function delete(User $user, Server $server): bool
    {
        return $this->roleInOrganization($user, $server->organization) === TeamRole::OWNER;
    }

    public function provision(User $user, Server $server): bool
    {
        return in_array($this->roleInOrganization($user, $server->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function runCommands(User $user, Server $server): bool
    {
        $role = $this->roleInOrganization($user, $server->organization);

        if (in_array($role, [TeamRole::OWNER, TeamRole::ADMIN], true)) {
            return true;
        }

        if ($role !== TeamRole::DEVELOPER) {
            return false;
        }

        $environmentName = strtolower((string) $server->environment?->name);

        return in_array($environmentName, ['staging', 'stage', 'development', 'dev'], true);
    }

    private function roleInOrganization(User $user, Organization $org): ?TeamRole
    {
        return $user->roleInOrganization($org);
    }
}
