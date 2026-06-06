<?php

declare(strict_types=1);

namespace App\Modules\Teams\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Models\Team;
use Illuminate\Validation\ValidationException;

class AddTeamMemberAction
{
    public function execute(Team $team, User $actor, User $member, TeamRole $role): void
    {
        if ($role === TeamRole::OWNER) {
            throw ValidationException::withMessages([
                'role' => ['Cannot assign owner role at the team level.'],
            ]);
        }

        $isOrgMember = $team->organization
            ->users()
            ->whereKey($member->getKey())
            ->exists();

        if (! $isOrgMember) {
            throw ValidationException::withMessages([
                'userId' => ['User must be a member of the organization before joining a team.'],
            ]);
        }

        if ($team->users()->whereKey($member->getKey())->exists()) {
            throw ValidationException::withMessages([
                'userId' => ['User is already a member of this team.'],
            ]);
        }

        $team->users()->attach($member->getKey(), ['role' => $role->value]);

        AuditLog::record(
            operation: 'team.member_added',
            resource: $team,
            metadata: [
                'actor_id' => (string) $actor->getKey(),
                'member_id' => (string) $member->getKey(),
            ],
            afterState: [
                'role' => $role->value,
            ],
        );
    }
}
