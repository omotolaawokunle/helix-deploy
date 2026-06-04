<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Policies;

use App\Models\User;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;

class DeploymentPolicy
{
    public function view(User $user, Deployment $deployment): bool
    {
        return $this->roleInOrganization($user, $deployment->organization_id) !== null;
    }

    public function viewLogs(User $user, Deployment $deployment): bool
    {
        return $this->view($user, $deployment);
    }

    public function execute(User $user, Site $site): bool
    {
        $role = $this->roleInOrganization($user, $site->organization_id);

        if ($role === null) {
            return false;
        }

        if (in_array($role, [TeamRole::OWNER, TeamRole::ADMIN], true)) {
            return true;
        }

        if ($role !== TeamRole::DEVELOPER) {
            return false;
        }

        $site->loadMissing('environment');
        $environment = $site->environment;

        return $environment === null || ! $environment->is_production;
    }

    public function rollback(User $user, Deployment $deployment): bool
    {
        $role = $this->roleInOrganization($user, $deployment->organization_id);

        if ($role === null) {
            return false;
        }

        if (in_array($role, [TeamRole::OWNER, TeamRole::ADMIN], true)) {
            return true;
        }

        if ($role !== TeamRole::DEVELOPER) {
            return false;
        }

        $deployment->loadMissing('site.environment');
        $environment = $deployment->site?->environment;

        return $environment === null || ! $environment->is_production;
    }

    public function cancel(User $user, Deployment $deployment): bool
    {
        return in_array($this->roleInOrganization($user, $deployment->organization_id), [
            TeamRole::OWNER,
            TeamRole::ADMIN,
        ], true);
    }

    private function roleInOrganization(User $user, string $organizationId): ?TeamRole
    {
        $org = $user->organizations()->whereKey($organizationId)->first();

        if ($org === null) {
            return null;
        }

        return $user->roleInOrganization($org);
    }
}
