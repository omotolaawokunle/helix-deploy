<?php

declare(strict_types=1);

namespace App\Modules\Projects\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Enums\TeamRole;

class EnvironmentPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->roleInOrganization($user, $project->organization) !== null;
    }

    public function view(User $user, Environment $environment): bool
    {
        return $this->roleInOrganization($user, $environment->organization) !== null;
    }

    public function create(User $user, Project $project): bool
    {
        return in_array($this->roleInOrganization($user, $project->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function update(User $user, Environment $environment): bool
    {
        return in_array($this->roleInOrganization($user, $environment->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function delete(User $user, Environment $environment): bool
    {
        return in_array($this->roleInOrganization($user, $environment->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    private function roleInOrganization(User $user, Organization $org): ?TeamRole
    {
        return $user->roleInOrganization($org);
    }
}
