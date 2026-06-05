<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Enums\ManagementMode;
use App\Modules\Servers\Enums\ServerProvider;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Str;

it('filters project and server listings by scoped team membership', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Visibility Org',
        'slug' => 'visibility-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $allowedProject = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Allowed Project',
        'description' => null,
    ]);
    $blockedProject = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Blocked Project',
        'description' => null,
    ]);

    $team = Team::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Scoped Team',
        'slug' => 'scoped-team',
    ]);
    $team->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);
    $team->projects()->attach($allowedProject->getKey());

    Server::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $allowedProject->getKey(),
        'hostname' => 'allowed.example.test',
        'ip_address' => '203.0.113.10',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => ServerProvider::GENERIC->value,
        'status' => ServerStatus::ACTIVE->value,
        'management_mode' => ManagementMode::MANAGED->value,
        'created_by' => (string) $owner->getKey(),
    ]);
    Server::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $blockedProject->getKey(),
        'hostname' => 'blocked.example.test',
        'ip_address' => '203.0.113.11',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => ServerProvider::GENERIC->value,
        'status' => ServerStatus::ACTIVE->value,
        'management_mode' => ManagementMode::MANAGED->value,
        'created_by' => (string) $owner->getKey(),
    ]);

    $this->actingAs($developer)
        ->getJson("/api/v1/organizations/{$organization->id}/projects")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Allowed Project');

    $this->actingAs($developer)
        ->getJson("/api/v1/organizations/{$organization->id}/servers")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.hostname', 'allowed.example.test');

    $this->actingAs($developer)
        ->getJson("/api/v1/projects/{$blockedProject->id}")
        ->assertForbidden();

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/projects")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('allows unrestricted access for team members on teams without project scope', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Unrestricted Org',
        'slug' => 'unrestricted-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Project One',
        'description' => null,
    ]);
    Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Project Two',
        'description' => null,
    ]);

    $team = Team::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Open Team',
        'slug' => 'open-team',
    ]);
    $team->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $this->actingAs($developer)
        ->getJson("/api/v1/organizations/{$organization->id}/projects")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
