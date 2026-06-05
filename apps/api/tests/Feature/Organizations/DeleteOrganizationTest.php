<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

it('owner with multiple organizations can delete an organization', function (): void {
    $firstOrg = Organization::query()->create([
        'name' => 'Primary Org',
        'slug' => 'primary-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $firstOrg->generateAndStoreMasterKey();

    $secondOrg = Organization::query()->create([
        'name' => 'Secondary Org',
        'slug' => 'secondary-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $secondOrg->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $firstOrg->getKey(),
    ]);

    $firstOrg->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);
    $secondOrg->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/organizations/{$firstOrg->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('organizations', [
        'id' => (string) $firstOrg->getKey(),
    ]);

    $this->assertDatabaseHas('users', [
        'id' => (string) $owner->getKey(),
        'current_organization_id' => (string) $secondOrg->getKey(),
    ]);

    expect(AuditLog::query()
        ->withoutGlobalScope('owned_by_organization')
        ->where('operation', 'organization.deleted')
        ->where('organization_id', (string) $firstOrg->getKey())
        ->exists())->toBeTrue();
});

it('owner cannot delete their only organization', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Solo Org',
        'slug' => 'solo-org-'.Str::random(6),
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
        ->deleteJson("/api/v1/organizations/{$organization->id}")
        ->assertForbidden();
});

it('non owner cannot delete an organization', function (): void {
    $firstOrg = Organization::query()->create([
        'name' => 'Protected Org',
        'slug' => 'protected-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $firstOrg->generateAndStoreMasterKey();

    $secondOrg = Organization::query()->create([
        'name' => 'Other Org',
        'slug' => 'other-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $secondOrg->generateAndStoreMasterKey();

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $firstOrg->getKey(),
    ]);

    $firstOrg->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);
    $firstOrg->users()->attach($admin->getKey(), ['role' => TeamRole::ADMIN->value]);
    $secondOrg->users()->attach($admin->getKey(), ['role' => TeamRole::OWNER->value]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/organizations/{$firstOrg->id}")
        ->assertForbidden();
});
