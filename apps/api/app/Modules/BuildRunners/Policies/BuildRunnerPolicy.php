<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Policies;

use App\Models\User;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;

class BuildRunnerPolicy
{
    public function viewAny(User $user, Organization $org): bool
    {
        return $this->roleInOrganization($user, $org) !== null;
    }

    public function view(User $user, BuildRunner $runner): bool
    {
        return $this->roleInOrganization($user, $runner->organization) !== null;
    }

    public function create(User $user, Organization $org): bool
    {
        return in_array($this->roleInOrganization($user, $org), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function update(User $user, BuildRunner $runner): bool
    {
        return in_array($this->roleInOrganization($user, $runner->organization), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function delete(User $user, BuildRunner $runner): bool
    {
        if ($this->roleInOrganization($user, $runner->organization) !== TeamRole::OWNER) {
            return false;
        }

        return ! app(RunnerSlotManager::class)->hasActiveSlots($runner);
    }

    private function roleInOrganization(User $user, Organization $org): ?TeamRole
    {
        return $user->roleInOrganization($org);
    }
}
