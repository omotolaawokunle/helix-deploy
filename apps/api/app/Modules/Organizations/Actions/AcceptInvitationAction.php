<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Validation\ValidationException;

class AcceptInvitationAction
{
    public function execute(Organization $organization, User $user, string $email, TeamRole $role): void
    {
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

        AuditLog::record(
            operation: 'member.invitation_accepted',
            resource: $organization,
            metadata: [
                'organization_id' => (string) $organization->getKey(),
                'user_id' => (string) $user->getKey(),
            ],
            afterState: [
                'email' => $email,
                'role' => $role->value,
            ],
        );
    }
}
