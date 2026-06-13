<?php

declare(strict_types=1);

namespace App\Modules\Sites\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use App\Modules\Deployments\Policies\DeploymentPolicy;
use App\Modules\Teams\Enums\TeamRole;

class SitePolicy
{
    public function viewAny(User $user, Organization $org): bool
    {
        return $this->roleInOrganization($user, $org) !== null;
    }

    public function view(User $user, Site $site): bool
    {
        return $this->roleInOrganization($user, $site->organization) !== null;
    }

    public function create(User $user, Organization $org): bool
    {
        $role = $this->roleInOrganization($user, $org);

        return in_array($role, [TeamRole::OWNER, TeamRole::ADMIN, TeamRole::DEVELOPER], true);
    }

    public function update(User $user, Site $site): bool
    {
        return in_array($this->roleInOrganization($user, $site->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function delete(User $user, Site $site): bool
    {
        return in_array($this->roleInOrganization($user, $site->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function deploy(User $user, Site $site): bool
    {
        return $this->execute($user, $site);
    }

    public function execute(User $user, Site $site): bool
    {
        return app(DeploymentPolicy::class)->execute($user, $site);
    }

    public function updateNginxConfig(User $user, Site $site): bool
    {
        return $this->update($user, $site);
    }

    public function manageEnvVars(User $user, Site $site): bool
    {
        return $this->update($user, $site);
    }

    public function revealEnvVar(User $user, Site $site): bool
    {
        return $this->update($user, $site);
    }

    public function syncEnvVars(User $user, Site $site): bool
    {
        return $this->update($user, $site);
    }

    public function pullEnvVars(User $user, Site $site): bool
    {
        return $this->update($user, $site);
    }

    public function retryDns(User $user, Site $site): bool
    {
        return in_array($this->roleInOrganization($user, $site->organization), [TeamRole::OWNER, TeamRole::ADMIN, TeamRole::DEVELOPER], true);
    }

    public function retrySsl(User $user, Site $site): bool
    {
        return in_array($this->roleInOrganization($user, $site->organization), [TeamRole::OWNER, TeamRole::ADMIN, TeamRole::DEVELOPER], true);
    }

    public function renewSsl(User $user, Site $site): bool
    {
        return in_array($this->roleInOrganization($user, $site->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function viewLogs(User $user, Site $site): bool
    {
        return $this->view($user, $site);
    }

    private function roleInOrganization(User $user, Organization $org): ?TeamRole
    {
        return $user->roleInOrganization($org);
    }
}
