<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Policies;

use App\Models\User;
use App\Modules\CronJobs\Models\CronJob;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;

class CronJobPolicy
{
    public function viewAny(User $user, Server $server): bool
    {
        return $this->roleInOrganization($user, $server->organization) !== null;
    }

    public function view(User $user, CronJob $cronJob): bool
    {
        return $this->roleInOrganization($user, $cronJob->organization) !== null;
    }

    public function create(User $user, Server $server): bool
    {
        return in_array($this->roleInOrganization($user, $server->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function update(User $user, CronJob $cronJob): bool
    {
        return in_array($this->roleInOrganization($user, $cronJob->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function delete(User $user, CronJob $cronJob): bool
    {
        return $this->update($user, $cronJob);
    }

    public function toggle(User $user, CronJob $cronJob): bool
    {
        return $this->update($user, $cronJob);
    }

    public function sync(User $user, Server $server): bool
    {
        return $this->create($user, $server);
    }

    private function roleInOrganization(User $user, Organization $org): ?TeamRole
    {
        return $user->roleInOrganization($org);
    }
}
