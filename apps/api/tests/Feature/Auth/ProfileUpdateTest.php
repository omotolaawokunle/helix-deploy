<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Auth\Notifications\QueuedVerifyEmail;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{Organization, User}
 */
function createProfileUpdateFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Profile Org',
        'slug' => 'profile-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'profile@example.test',
        'password' => 'old-password-123',
        'timezone' => 'UTC',
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($user->getKey(), ['role' => TeamRole::OWNER->value]);

    return [$organization, $user];
}

it('updates profile name and timezone', function (): void {
    [, $user] = createProfileUpdateFixture();

    $this->actingAs($user)
        ->patchJson('/api/v1/auth/user', [
            'name' => 'Updated Name',
            'email' => 'profile@example.test',
            'timezone' => 'Europe/London',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.timezone', 'Europe/London');

    expect(AuditLog::query()->where('operation', 'user.profile_updated')->exists())->toBeTrue();
});

it('requires re-verification when email changes', function (): void {
    Queue::fake();
    [, $user] = createProfileUpdateFixture();

    $this->actingAs($user)
        ->patchJson('/api/v1/auth/user', [
            'name' => 'Updated Name',
            'email' => 'new-email@example.test',
            'timezone' => 'UTC',
        ])
        ->assertOk()
        ->assertJsonPath('data.email', 'new-email@example.test')
        ->assertJsonPath('data.emailVerifiedAt', null);

    $user->refresh();

    expect($user->email)->toBe('new-email@example.test')
        ->and($user->email_verified_at)->toBeNull();

    Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job) use ($user): bool {
        return $job->notification instanceof QueuedVerifyEmail
            && $job->notifiables->first()?->is($user);
    });
});

it('validates profile update input', function (): void {
    [, $user] = createProfileUpdateFixture();

    $this->actingAs($user)
        ->patchJson('/api/v1/auth/user', [
            'name' => '',
            'email' => 'not-an-email',
            'timezone' => 'Invalid/Timezone',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'timezone']);
});

it('rejects duplicate email on profile update', function (): void {
    [, $user] = createProfileUpdateFixture();

    User::factory()->create([
        'email' => 'taken@example.test',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->patchJson('/api/v1/auth/user', [
            'name' => 'Updated Name',
            'email' => 'taken@example.test',
            'timezone' => 'UTC',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('changes password with valid current password', function (): void {
    [, $user] = createProfileUpdateFixture();

    $this->actingAs($user)
        ->postJson('/api/v1/auth/password', [
            'currentPassword' => 'old-password-123',
            'password' => 'new-password-456',
            'passwordConfirmation' => 'new-password-456',
        ])
        ->assertNoContent();

    $user->refresh();

    expect(Hash::check('new-password-456', (string) $user->password))->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'user.password_changed')->exists())->toBeTrue();
});

it('rejects password change when current password is wrong', function (): void {
    [, $user] = createProfileUpdateFixture();

    $this->actingAs($user)
        ->postJson('/api/v1/auth/password', [
            'currentPassword' => 'wrong-password',
            'password' => 'new-password-456',
            'passwordConfirmation' => 'new-password-456',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currentPassword']);
});

it('validates password change input', function (): void {
    [, $user] = createProfileUpdateFixture();

    $this->actingAs($user)
        ->postJson('/api/v1/auth/password', [
            'currentPassword' => 'old-password-123',
            'password' => 'short',
            'passwordConfirmation' => 'mismatch',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});
