<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Jobs\SendInvitationEmailJob;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;

it('owner can invite change role and remove member', function (): void {
    Queue::fake();

    $organization = Organization::query()->create([
        'name' => 'Owner Org',
        'slug' => 'owner-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $member = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);
    $organization->users()->attach($member->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/invitations", [
            'email' => 'invited@example.test',
            'role' => TeamRole::DEVELOPER->value,
        ])
        ->assertCreated();

    Queue::assertPushed(SendInvitationEmailJob::class);

    $this->actingAs($owner)
        ->patchJson("/api/v1/organizations/{$organization->id}/members/{$member->id}", [
            'role' => TeamRole::ADMIN->value,
        ])
        ->assertNoContent();

    $this->assertDatabaseHas('organization_users', [
        'organization_id' => (string) $organization->getKey(),
        'user_id' => (string) $member->getKey(),
        'role' => TeamRole::ADMIN->value,
    ]);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/organizations/{$organization->id}/members/{$member->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('organization_users', [
        'organization_id' => (string) $organization->getKey(),
        'user_id' => (string) $member->getKey(),
    ]);
});

it('non owner gets forbidden on member management actions', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Team Org',
        'slug' => 'team-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $member = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);
    $organization->users()->attach($member->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $this->actingAs($member)
        ->postJson("/api/v1/organizations/{$organization->id}/invitations", [
            'email' => 'blocked@example.test',
            'role' => TeamRole::DEVELOPER->value,
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->patchJson("/api/v1/organizations/{$organization->id}/members/{$owner->id}", [
            'role' => TeamRole::VIEWER->value,
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->deleteJson("/api/v1/organizations/{$organization->id}/members/{$owner->id}")
        ->assertForbidden();
});

it('last owner cannot be demoted or removed', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Solo Owner Org',
        'slug' => 'solo-owner-org',
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
        ->patchJson("/api/v1/organizations/{$organization->id}/members/{$owner->id}", [
            'role' => TeamRole::ADMIN->value,
        ])
        ->assertUnprocessable();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/organizations/{$organization->id}/members/{$owner->id}")
        ->assertUnprocessable();
});

it('cross organization access is forbidden', function (): void {
    $firstOrg = Organization::query()->create([
        'name' => 'First Org',
        'slug' => 'first-cross-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $firstOrg->generateAndStoreMasterKey();

    $secondOrg = Organization::query()->create([
        'name' => 'Second Org',
        'slug' => 'second-cross-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $secondOrg->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $firstOrg->getKey(),
    ]);

    $firstOrg->users()->attach($user->getKey(), ['role' => TeamRole::OWNER->value]);

    $this->actingAs($user)
        ->getJson("/api/v1/organizations/{$secondOrg->id}")
        ->assertForbidden();
});

it('switch organization sets current organization id on user', function (): void {
    $firstOrg = Organization::query()->create([
        'name' => 'First Switch Org',
        'slug' => 'first-switch-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $firstOrg->generateAndStoreMasterKey();

    $secondOrg = Organization::query()->create([
        'name' => 'Second Switch Org',
        'slug' => 'second-switch-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $secondOrg->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $firstOrg->getKey(),
    ]);

    $firstOrg->users()->attach($user->getKey(), ['role' => TeamRole::OWNER->value]);
    $secondOrg->users()->attach($user->getKey(), ['role' => TeamRole::OWNER->value]);

    $this->actingAs($user)
        ->postJson("/api/v1/organizations/{$secondOrg->id}/switch")
        ->assertNoContent();

    $this->assertDatabaseHas('users', [
        'id' => (string) $user->getKey(),
        'current_organization_id' => (string) $secondOrg->getKey(),
    ]);
});

it('owned by organization scope reflects switched organization', function (): void {
    $firstOrg = Organization::query()->create([
        'name' => 'Scope First Org',
        'slug' => 'scope-first-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $firstOrg->generateAndStoreMasterKey();

    $secondOrg = Organization::query()->create([
        'name' => 'Scope Second Org',
        'slug' => 'scope-second-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $secondOrg->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $firstOrg->getKey(),
    ]);

    $firstOrg->users()->attach($user->getKey(), ['role' => TeamRole::OWNER->value]);
    $secondOrg->users()->attach($user->getKey(), ['role' => TeamRole::OWNER->value]);

    Project::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $firstOrg->getKey(),
        'name' => 'Project A',
        'description' => null,
    ]);

    Project::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $secondOrg->getKey(),
        'name' => 'Project B',
        'description' => null,
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/organizations/{$secondOrg->id}/switch")
        ->assertNoContent();

    $projectNames = Project::query()->pluck('name')->all();

    expect($projectNames)->toBe(['Project B']);
});

it('organizations index paginates by default and supports search and sort', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    foreach (range(1, 18) as $index) {
        $organization = Organization::query()->create([
            'name' => "Team {$index}",
            'slug' => "team-{$index}",
            'master_key_encrypted' => '{}',
            'settings' => [],
        ]);
        $organization->generateAndStoreMasterKey();
        $organization->users()->attach($user->getKey(), ['role' => TeamRole::OWNER->value]);
    }

    $this->actingAs($user)
        ->getJson('/api/v1/organizations')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonCount(15, 'data');

    $this->actingAs($user)
        ->getJson('/api/v1/organizations?search=Team%2018&sort=organizations.name')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Team 18');
});

it('organization members endpoint supports pagination filter and sort', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Members Org',
        'slug' => 'members-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'name' => 'Owner User',
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $admin = User::factory()->create([
        'name' => 'Admin User',
        'email_verified_at' => now(),
    ]);
    $developer = User::factory()->create([
        'name' => 'Developer User',
        'email_verified_at' => now(),
    ]);

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);
    $organization->users()->attach($admin->getKey(), ['role' => TeamRole::ADMIN->value]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/members?per_page=2")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonCount(2, 'data');

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/members?filter[role]=admin")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.role', TeamRole::ADMIN->value);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/members?sort=-users.name")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Owner User');
});
