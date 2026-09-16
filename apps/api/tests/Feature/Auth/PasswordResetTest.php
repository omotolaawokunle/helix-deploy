<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Auth\Notifications\QueuedResetPassword;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;

function createPasswordResetFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Reset Org',
        'slug' => 'reset-org-'.uniqid(),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $user = User::query()->create([
        'name' => 'Reset User',
        'email' => 'reset-'.uniqid().'@example.test',
        'password' => 'old-password-123',
        'timezone' => 'UTC',
        'current_organization_id' => $organization->getKey(),
        'email_verified_at' => now(),
    ]);

    $organization->users()->attach($user->getKey(), [
        'role' => TeamRole::OWNER->value,
    ]);

    return [$organization, $user];
}

it('always returns 204 for forgot password even when email is unknown', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'nobody@example.test',
    ])->assertNoContent();

    Notification::assertNothingSent();
});

it('queues a reset password notification for a known email', function (): void {
    Notification::fake();
    [, $user] = createPasswordResetFixture();

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => $user->email,
    ])->assertNoContent();

    Notification::assertSentTo($user, QueuedResetPassword::class);
});

it('builds a spa reset url in the notification', function (): void {
    config([
        'helixdeploy.spa_url' => 'https://app.helix.test',
    ]);

    [, $user] = createPasswordResetFixture();

    $notification = new QueuedResetPassword('test-token-value');
    $mail = $notification->toMail($user);
    $url = $mail->actionUrl;

    expect($url)->toStartWith('https://app.helix.test/reset-password?')
        ->and($url)->toContain('token=test-token-value')
        ->and($url)->toContain('email='.urlencode($user->email));
});

it('resets password with a valid token and revokes api tokens', function (): void {
    [, $user] = createPasswordResetFixture();

    $token = Password::broker()->createToken($user);
    $user->createToken('cli')->plainTextToken;

    expect(PersonalAccessToken::query()->where('tokenable_id', $user->getKey())->count())->toBe(1);

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password-456',
        'passwordConfirmation' => 'new-password-456',
    ])->assertNoContent();

    $user->refresh();

    expect(Hash::check('new-password-456', (string) $user->password))->toBeTrue()
        ->and(PersonalAccessToken::query()->where('tokenable_id', $user->getKey())->count())->toBe(0)
        ->and(AuditLog::query()->where('operation', 'user.password_reset')->exists())->toBeTrue();
});

it('rejects an invalid reset token', function (): void {
    [, $user] = createPasswordResetFixture();

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => $user->email,
        'token' => 'invalid-token',
        'password' => 'new-password-456',
        'passwordConfirmation' => 'new-password-456',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('validates forgot password input', function (): void {
    $this->postJson('/api/v1/auth/forgot-password', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('validates reset password input', function (): void {
    $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'not-an-email',
        'token' => '',
        'password' => 'short',
        'passwordConfirmation' => 'mismatch',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'token', 'password']);
});

it('deletes database sessions for the user on reset', function (): void {
    config(['session.driver' => 'database']);

    [, $user] = createPasswordResetFixture();
    $token = Password::broker()->createToken($user);

    DB::table('sessions')->insert([
        'id' => 'session-to-kill',
        'user_id' => (string) $user->getKey(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password-456',
        'passwordConfirmation' => 'new-password-456',
    ])->assertNoContent();

    expect(DB::table('sessions')->where('id', 'session-to-kill')->exists())->toBeFalse();
});
