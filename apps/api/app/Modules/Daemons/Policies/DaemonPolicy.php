<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Policies;

use App\Models\User;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;

class DaemonPolicy
{
    public function viewAny(User $user, Server $server): bool
    {
        return $this->roleInOrganization($user, $server->organization) !== null;
    }

    public function view(User $user, SupervisorProcess $daemon): bool
    {
        return $this->roleInOrganization($user, $daemon->organization) !== null;
    }

    public function create(User $user, Server $server): bool
    {
        return in_array($this->roleInOrganization($user, $server->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function manage(User $user, SupervisorProcess $daemon): bool
    {
        return in_array($this->roleInOrganization($user, $daemon->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function delete(User $user, SupervisorProcess $daemon): bool
    {
        return $this->manage($user, $daemon);
    }

    private function roleInOrganization(User $user, Organization $org): ?TeamRole
    {
        return $user->roleInOrganization($org);
    }
}
