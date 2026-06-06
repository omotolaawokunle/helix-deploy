<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Jobs\SendInvitationEmailJob;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Services\InvitationTokenService;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class InviteMemberAction
{
    public function __construct(
        private readonly InvitationTokenService $invitationTokenService,
    ) {
    }

    public function execute(Organization $organization, User $actor, string $email, TeamRole $role): string
    {
        if ($role === TeamRole::OWNER) {
            throw ValidationException::withMessages([
                'role' => ['Cannot invite a member as owner. Transfer ownership instead.'],
            ]);
        }

        $existingMember = $organization->users()
            ->where('email', $email)
            ->exists();

        if ($existingMember) {
            throw ValidationException::withMessages([
                'email' => ['User is already a member of this organization.'],
            ]);
        }

        $expiration = now()->addDays(7);

        $token = $this->invitationTokenService->encode(
            organizationId: (string) $organization->getKey(),
            email: $email,
            role: $role,
        );

        $invitationUrl = URL::temporarySignedRoute(
            name: 'organizations.invitations.accept',
            expiration: $expiration,
            parameters: [
                'token' => $token,
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
                'role' => $role->value,
            ],
        );

        return $invitationUrl;
    }
}
