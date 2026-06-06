<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Auth\Notifications\QueuedVerifyEmail;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('creates user organization and pivot record on register', function (): void {
    Queue::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.test',
        'password' => 'secret-pass',
        'organization_id' => (string) Str::uuid(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'jane@example.test');

    $user = User::query()->where('email', 'jane@example.test')->firstOrFail();
    $organization = Organization::query()->findOrFail($user->current_organization_id);

    expect($organization->name)->toBe("Jane Doe's Organization")
        ->and($organization->master_key_encrypted)->not->toBe('{}');

    $this->assertDatabaseHas('organization_users', [
        'organization_id' => (string) $organization->getKey(),
        'user_id' => (string) $user->getKey(),
        'role' => TeamRole::OWNER->value,
    ]);

    Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job) use ($user): bool {
        return $job->notification instanceof QueuedVerifyEmail
            && $job->notifiables->first()?->is($user);
    });
});

it('allows unverified users to fetch auth user and logout', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Acme Org',
        'slug' => 'acme-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->unverified()->create([
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($user->getKey(), [
        'role' => TeamRole::OWNER->value,
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/auth/user')
        ->assertOk()
        ->assertJsonPath('data.emailVerifiedAt', null);

    $this->actingAs($user)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();
});

it('forbids unverified users from verified application routes', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Acme Org',
        'slug' => 'acme-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->unverified()->create([
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($user->getKey(), [
        'role' => TeamRole::OWNER->value,
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/organizations')
        ->assertForbidden();
});

it('returns user with current organization on login', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Login Org',
        'slug' => 'login-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email' => 'john@example.test',
        'password' => 'password-123',
        'current_organization_id' => (string) $organization->getKey(),
        'email_verified_at' => now(),
    ]);

    $organization->users()->attach($user->getKey(), [
        'role' => TeamRole::OWNER->value,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'john@example.test',
        'password' => 'password-123',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $user->getKey())
        ->assertJsonPath('data.currentOrganization.id', (string) $organization->getKey());
});

it('returns unauthorized when login password is wrong', function (): void {
    $user = User::factory()->create([
        'email' => 'wrongpass@example.test',
        'password' => 'password-123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => (string) $user->email,
        'password' => 'not-the-password',
    ])->assertUnauthorized();
});

it('resolves organization from authenticated user on subsequent requests', function (): void {
    $firstOrganization = Organization::query()->create([
        'name' => 'First Org',
        'slug' => 'first-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $firstOrganization->generateAndStoreMasterKey();

    $secondOrganization = Organization::query()->create([
        'name' => 'Second Org',
        'slug' => 'second-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $secondOrganization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'current_organization_id' => (string) $secondOrganization->getKey(),
        'email_verified_at' => now(),
    ]);

    $firstOrganization->users()->attach($user->getKey(), ['role' => TeamRole::DEVELOPER->value]);
    $secondOrganization->users()->attach($user->getKey(), ['role' => TeamRole::OWNER->value]);

    $this->actingAs($user)
        ->getJson('/api/v1/auth/user?organization_id='.(string) $firstOrganization->getKey())
        ->assertOk()
        ->assertJsonPath('data.currentOrganizationId', (string) $secondOrganization->getKey())
        ->assertJsonPath('data.currentOrganization.id', (string) $secondOrganization->getKey());
});
