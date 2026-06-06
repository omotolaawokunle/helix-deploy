<?php

declare(strict_types=1);

namespace App\Modules\Teams\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Models\Team;
use Illuminate\Validation\ValidationException;

class RemoveTeamMemberAction
{
    public function execute(Team $team, User $actor, User $member): void
    {
        $memberModel = $team->users()
            ->whereKey($member->getKey())
            ->first();

        if ($memberModel === null) {
            throw ValidationException::withMessages([
                'user' => ['User is not a member of this team.'],
            ]);
        }

        $role = TeamRole::from((string) $memberModel->pivot?->role);

        $team->users()->detach($member->getKey());

        AuditLog::record(
            operation: 'team.member_removed',
            resource: $team,
            metadata: [
                'actor_id' => (string) $actor->getKey(),
                'member_id' => (string) $member->getKey(),
            ],
            beforeState: [
                'role' => $role->value,
            ],
        );
    }
}
