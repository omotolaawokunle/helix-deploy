<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Validation\ValidationException;

class ChangeMemberRoleAction
{
    public function execute(Organization $organization, User $actor, User $member, TeamRole $newRole): void
    {
        $memberModel = $organization->users()
            ->whereKey($member->getKey())
            ->first();

        if ($memberModel === null) {
            throw ValidationException::withMessages([
                'user' => ['User is not a member of this organization.'],
            ]);
        }

        $oldRole = TeamRole::from((string) $memberModel->pivot?->role);

        if ($oldRole === TeamRole::OWNER && $newRole !== TeamRole::OWNER && $this->ownerCount($organization) <= 1) {
            throw ValidationException::withMessages([
                'role' => ['Cannot demote the last owner.'],
            ]);
        }

        $organization->users()->updateExistingPivot($member->getKey(), [
            'role' => $newRole->value,
        ]);

        AuditLog::record(
            operation: 'member.role_changed',
            resource: $organization,
            metadata: [
                'organization_id' => (string) $organization->getKey(),
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

    private function ownerCount(Organization $organization): int
    {
        return $organization->users()
            ->wherePivot('role', TeamRole::OWNER->value)
            ->count();
    }
}
