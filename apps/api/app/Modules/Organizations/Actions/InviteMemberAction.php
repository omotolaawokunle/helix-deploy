<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Jobs\SendInvitationEmailJob;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class InviteMemberAction
{
    public function execute(Organization $organization, User $actor, string $email): string
    {
        $existingMember = $organization->users()
            ->where('email', $email)
            ->exists();

        if ($existingMember) {
            throw ValidationException::withMessages([
                'email' => ['User is already a member of this organization.'],
            ]);
        }

        $invitationUrl = URL::temporarySignedRoute(
            name: 'organizations.invitations.accept',
            expiration: now()->addDays(7),
            parameters: [
                'organization' => (string) $organization->getKey(),
                'email' => $email,
            ],
        );

        SendInvitationEmailJob::dispatch($email, $invitationUrl);

        AuditLog::record(
            operation: 'member.invited',
            resource: $organization,
            metadata: [
                'organization_id' => (string) $organization->getKey(),
                'actor_id' => (string) $actor->getKey(),
                'email' => $email,
            ],
            afterState: [
                'email' => $email,
            ],
        );

        return $invitationUrl;
    }
}
