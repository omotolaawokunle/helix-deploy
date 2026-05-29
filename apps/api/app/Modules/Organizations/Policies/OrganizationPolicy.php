<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $this->isMember($user, $organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization);
    }

    public function delete(User $user, Organization $organization): bool
    {
        if (! $this->isOwner($user, $organization)) {
            return false;
        }

        return $user->organizations()->count() > 1;
    }

    private function isMember(User $user, Organization $organization): bool
    {
        return $organization->users()
            ->whereKey($user->getKey())
            ->exists();
    }

    private function isOwner(User $user, Organization $organization): bool
    {
        if (! $this->isMember($user, $organization)) {
            return false;
        }

        $role = $organization->users()
            ->whereKey($user->getKey())
            ->first()?->pivot?->role;

        return $role === TeamRole::OWNER->value;
    }
}
