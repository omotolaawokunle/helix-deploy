<?php

declare(strict_types=1);

namespace App\Modules\Sites\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\GitProviderIntegration;
use App\Modules\Teams\Enums\TeamRole;

class GitProviderPolicy
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
        return $user->roleInOrganization($org);
    }
}
