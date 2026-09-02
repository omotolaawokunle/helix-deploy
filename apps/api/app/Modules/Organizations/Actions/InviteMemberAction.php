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
    ) {}

    public function execute(
        Organization $organization,
        User $actor,
        string $email,
        TeamRole $role,
        ?string $teamId = null,
    ): string {
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
            teamId: $teamId,
        );

        $invitationUrl = URL::temporarySignedRoute(
            name: 'organizations.invitations.accept',
            expiration: $expiration,
            parameters: [
                'token' => $token,
            ],
        );

        SendInvitationEmailJob::dispatch(
            $email,
            $invitationUrl,
            $organization->name,
            $actor->name,
            $this->teamNameForInvitation($organization, $teamId),
        );

        AuditLog::record(
            operation: 'member.invited',
            resource: $organization,
            metadata: [
                'organization_id' => (string) $organization->getKey(),
                'actor_id' => (string) $actor->getKey(),
                'email' => $email,
            ],
            afterState: array_filter([
                'email' => $email,
                'role' => $role->value,
                'team_id' => $teamId,
            ], static fn (mixed $value): bool => $value !== null),
        );

        return $invitationUrl;
    }

    private function teamNameForInvitation(Organization $organization, ?string $teamId): ?string
    {
        if ($teamId === null) {
            return null;
        }

        $teamName = $organization->teams()->whereKey($teamId)->value('name');

        if (! is_string($teamName) || $teamName === '') {
            throw ValidationException::withMessages([
                'teamId' => ['The selected team is invalid.'],
            ]);
        }

        return $teamName;
    }
}
