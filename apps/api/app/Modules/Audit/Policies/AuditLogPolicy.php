<?php

declare(strict_types=1);

namespace App\Modules\Audit\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;

final class AuditLogPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return in_array($this->roleInOrganization($user, $organization), [
            TeamRole::OWNER,
            TeamRole::ADMIN,
        ], true);
    }

    public function export(User $user, Organization $organization): bool
    {
        return $this->roleInOrganization($user, $organization) === TeamRole::OWNER;
    }

    public function viewSensitiveState(User $user, Organization $organization): bool
    {
        return $this->roleInOrganization($user, $organization) === TeamRole::OWNER;
    }

    private function roleInOrganization(User $user, Organization $organization): ?TeamRole
    {
        return $user->roleInOrganization($organization);
    }
}
