<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Services\InvitationTokenService;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\URL;

function makeInvitationAcceptUrl(
    string $organizationId,
    string $email,
    TeamRole $role,
    ?\DateTimeInterface $expiration = null,
): string {
    $token = app(InvitationTokenService::class)->encode(
        organizationId: $organizationId,
        email: $email,
        role: $role,
    );

    return URL::temporarySignedRoute(
        name: 'organizations.invitations.accept',
        expiration: $expiration ?? now()->addDays(7),
        parameters: [
            'token' => $token,
        ],
    );
}

it('accepts an encrypted invitation token and adds the user to the organization', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Invite Org',
        'slug' => 'invite-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $invitee = User::factory()->create([
        'email' => 'invitee@example.test',
        'email_verified_at' => now(),
    ]);

    $acceptUrl = makeInvitationAcceptUrl(
        organizationId: (string) $organization->getKey(),
        email: 'invitee@example.test',
        role: TeamRole::DEVELOPER,
    );

    expect($acceptUrl)->not->toContain('invitee@example.test')
        ->and($acceptUrl)->not->toContain((string) $organization->getKey())
        ->and($acceptUrl)->toContain('expires=')
        ->and($acceptUrl)->toContain('signature=');

    $this->actingAs($invitee)
        ->postJson($acceptUrl)
        ->assertOk()
        ->assertJsonPath('data.organizationId', (string) $organization->getKey())
        ->assertJsonPath('data.organizationName', 'Invite Org');

    $this->assertDatabaseHas('organization_users', [
        'organization_id' => (string) $organization->getKey(),
        'user_id' => (string) $invitee->getKey(),
        'role' => TeamRole::DEVELOPER->value,
    ]);

    $this->assertDatabaseHas('users', [
        'id' => (string) $invitee->getKey(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    expect(
        AuditLog::query()
            ->where('operation', 'member.invitation_accepted')
            ->where('organization_id', (string) $organization->getKey())
            ->exists(),
    )->toBeTrue();
});

it('rejects invitation when email does not match the authenticated user', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Mismatch Org',
        'slug' => 'mismatch-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email' => 'actual@example.test',
        'email_verified_at' => now(),
    ]);

    $acceptUrl = makeInvitationAcceptUrl(
        organizationId: (string) $organization->getKey(),
        email: 'other@example.test',
        role: TeamRole::VIEWER,
    );

    $this->actingAs($user)
        ->postJson($acceptUrl)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects expired or tampered invitation tokens', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Expired Org',
        'slug' => 'expired-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email' => 'invitee@example.test',
        'email_verified_at' => now(),
    ]);

    $expiredUrl = makeInvitationAcceptUrl(
        organizationId: (string) $organization->getKey(),
        email: 'invitee@example.test',
        role: TeamRole::ADMIN,
        expiration: now()->subMinute(),
    );

    $this->actingAs($user)
        ->postJson($expiredUrl)
        ->assertStatus(410);

    $validUrl = makeInvitationAcceptUrl(
        organizationId: (string) $organization->getKey(),
        email: 'invitee@example.test',
        role: TeamRole::ADMIN,
    );

    parse_str((string) parse_url($validUrl, PHP_URL_QUERY), $query);
    $query['signature'] = 'invalid-signature';

    $tamperedUrl = URL::route('organizations.invitations.accept', $query);

    $this->actingAs($user)
        ->postJson($tamperedUrl)
        ->assertForbidden();
});

it('owner can invite with role and accept redirect preserves encrypted token', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Redirect Org',
        'slug' => 'redirect-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/invitations", [
            'email' => 'newmember@example.test',
            'role' => TeamRole::ADMIN->value,
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['invitationUrl']]);

    $invitationUrl = (string) $response->json('data.invitationUrl');

    expect($invitationUrl)->toContain('token=')
        ->and($invitationUrl)->toContain('expires=')
        ->and($invitationUrl)->toContain('signature=')
        ->and($invitationUrl)->not->toContain('newmember@example.test');

    $this->get($invitationUrl)
        ->assertRedirect();
});

it('cannot invite a member as owner', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Owner Invite Org',
        'slug' => 'owner-invite-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/invitations", [
            'email' => 'newowner@example.test',
            'role' => TeamRole::OWNER->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});
