<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Policies;

use App\Models\User;
use App\Modules\Pipelines\Models\PipelineRun;
use App\Modules\Teams\Enums\TeamRole;

class PipelineRunPolicy
{
    public function approve(User $user, PipelineRun $run): bool
    {
        return $this->canDecideApproval($user, $run);
    }

    public function reject(User $user, PipelineRun $run): bool
    {
        return $this->canDecideApproval($user, $run);
    }

    private function canDecideApproval(User $user, PipelineRun $run): bool
    {
        $userRole = $user->roleInOrganization($run->organization);

        if ($userRole === null) {
            return false;
        }

        $step = $run->awaitingApprovalStep();
        $requiredRole = $step?->approver_role ?? TeamRole::ADMIN;

        return $this->roleMeetsMinimum($userRole, $requiredRole);
    }

    private function roleMeetsMinimum(TeamRole $userRole, TeamRole $requiredRole): bool
    {
        $rank = [
            TeamRole::VIEWER->value => 1,
            TeamRole::DEVELOPER->value => 2,
            TeamRole::ADMIN->value => 3,
            TeamRole::OWNER->value => 4,
        ];

        return ($rank[$userRole->value] ?? 0) >= ($rank[$requiredRole->value] ?? 0);
    }
}
