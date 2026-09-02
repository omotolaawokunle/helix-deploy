<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Actions\AddTeamMemberAction;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class AcceptInvitationAction
{
    public function __construct(
        private readonly AddTeamMemberAction $addTeamMemberAction,
    ) {
    }

    public function execute(
        Organization $organization,
        User $user,
        string $email,
        TeamRole $role,
        ?string $teamId = null,
    ): void {
        if (strcasecmp((string) $user->email, $email) !== 0) {
            throw ValidationException::withMessages([
                'email' => ['This invitation was sent to a different email address.'],
            ]);
        }

        $isMember = $organization->users()
            ->whereKey($user->getKey())
            ->exists();

        if ($isMember) {
            throw ValidationException::withMessages([
                'organization' => ['You are already a member of this organization.'],
            ]);
        }

        $organization->users()->attach($user->getKey(), [
            'role' => $role->value,
        ]);

        $user->forceFill([
            'current_organization_id' => (string) $organization->getKey(),
        ])->save();

        if ($teamId !== null) {
            $team = Team::query()
                ->withoutGlobalScope('owned_by_organization')
                ->whereKey($teamId)
                ->where('organization_id', (string) $organization->getKey())
                ->first();

            if ($team === null) {
                throw (new ModelNotFoundException())->setModel(Team::class, [$teamId]);
            }

            $this->addTeamMemberAction->execute(
                team: $team,
                actor: $user,
                member: $user,
                role: $role,
            );
        }

        AuditLog::record(
            operation: 'member.invitation_accepted',
            resource: $organization,
            metadata: array_filter([
                'organization_id' => (string) $organization->getKey(),
                'user_id' => (string) $user->getKey(),
                'team_id' => $teamId,
                'via_invitation' => true,
            ], static fn (mixed $value): bool => $value !== null && $value !== false),
            afterState: array_filter([
                'email' => $email,
                'role' => $role->value,
                'team_id' => $teamId,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }
}
