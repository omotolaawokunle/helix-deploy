<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Teams\Contracts\TeamProjectVisibilityServiceInterface;
use App\Modules\Teams\Enums\TeamRole;

class PipelinePolicy
{
    public function viewAny(User $user, Organization $org): bool
    {
        return $this->roleInOrganization($user, $org) !== null;
    }

    public function view(User $user, Pipeline $pipeline): bool
    {
        if ($this->roleInOrganization($user, $pipeline->organization) === null) {
            return false;
        }

        if ($pipeline->project_id === null) {
            return true;
        }

        $visibleProjectIds = app(TeamProjectVisibilityServiceInterface::class)
            ->visibleProjectIds($user, $pipeline->organization);

        if ($visibleProjectIds === null) {
            return true;
        }

        return in_array((string) $pipeline->project_id, $visibleProjectIds, true);
    }

    public function create(User $user, Organization $org): bool
    {
        return in_array($this->roleInOrganization($user, $org), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        return in_array(
            $this->roleInOrganization($user, $pipeline->organization),
            [TeamRole::OWNER, TeamRole::ADMIN],
            true,
        );
    }

    public function delete(User $user, Pipeline $pipeline): bool
    {
        return in_array(
            $this->roleInOrganization($user, $pipeline->organization),
            [TeamRole::OWNER, TeamRole::ADMIN],
            true,
        );
    }

    private function roleInOrganization(User $user, Organization $org): ?TeamRole
    {
        return $user->roleInOrganization($org);
    }
}
