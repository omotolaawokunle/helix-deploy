<?php

declare(strict_types=1);

namespace App\Modules\Teams\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Models\Team;
use Illuminate\Validation\ValidationException;

class ChangeTeamMemberRoleAction
{
    public function execute(Team $team, User $actor, User $member, TeamRole $newRole): void
    {
        if ($newRole === TeamRole::OWNER) {
            throw ValidationException::withMessages([
                'role' => ['Cannot assign owner role at the team level.'],
            ]);
        }

        $memberModel = $team->users()
            ->whereKey($member->getKey())
            ->first();

        if ($memberModel === null) {
            throw ValidationException::withMessages([
                'user' => ['User is not a member of this team.'],
            ]);
        }

        $oldRole = TeamRole::from((string) $memberModel->pivot?->role);

        $team->users()->updateExistingPivot($member->getKey(), [
            'role' => $newRole->value,
        ]);

        AuditLog::record(
            operation: 'team.member_role_changed',
            resource: $team,
            metadata: [
                'actor_id' => (string) $actor->getKey(),
                'member_id' => (string) $member->getKey(),
            ],
            beforeState: [
                'role' => $oldRole->value,
            ],
            afterState: [
                'role' => $newRole->value,
            ],
        );
    }
}
