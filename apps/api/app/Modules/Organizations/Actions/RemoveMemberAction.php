<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Validation\ValidationException;

class RemoveMemberAction
{
    public function execute(Organization $organization, User $actor, User $member): void
    {
        $memberModel = $organization->users()
            ->whereKey($member->getKey())
            ->first();

        if ($memberModel === null) {
            throw ValidationException::withMessages([
                'user' => ['User is not a member of this organization.'],
            ]);
        }

        $role = TeamRole::from((string) $memberModel->pivot?->role);

        if ($role === TeamRole::OWNER && $this->ownerCount($organization) <= 1) {
            throw ValidationException::withMessages([
                'user' => ['Cannot remove the last owner.'],
            ]);
        }

        $organization->users()->detach($member->getKey());

        if ((string) $member->current_organization_id === (string) $organization->getKey()) {
            $fallbackOrgId = $member->organizations()->value('organizations.id');
            $member->forceFill(['current_organization_id' => $fallbackOrgId])->save();
        }

        AuditLog::record(
            operation: 'member.removed',
            resource: $organization,
            metadata: [
                'organization_id' => (string) $organization->getKey(),
                'actor_id' => (string) $actor->getKey(),
                'member_id' => (string) $member->getKey(),
            ],
            beforeState: [
                'role' => $role->value,
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
