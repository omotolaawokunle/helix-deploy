<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;

final class DigitalOceanPolicy
{
    public function viewAny(User $user, Organization $org): bool
    {
        return $this->roleInOrganization($user, $org) !== null;
    }

    public function manage(User $user, Organization $org): bool
    {
        return in_array($this->roleInOrganization($user, $org), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    private function roleInOrganization(User $user, Organization $org): ?TeamRole
    {
        $role = $org->users()
            ->whereKey($user->getKey())
            ->value('role');

        return is_string($role) ? TeamRole::tryFrom($role) : null;
    }
}
