<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

/**
 * @return array{Organization, User}
 */
function createProjectApiFixture(TeamRole $role = TeamRole::OWNER): array
{
    $organization = Organization::query()->create([
        'name' => 'Projects API Org',
        'slug' => 'projects-api-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($user->getKey(), ['role' => $role->value]);

    return [$organization, $user];
}

it('lists projects for the current organization with counts', function (): void {
    [$organization, $owner] = createProjectApiFixture();

    Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Billing API',
        'description' => 'Client billing stack',
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/projects")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Billing API')
        ->assertJsonPath('data.0.description', 'Client billing stack');
});

it('creates updates and deletes a project with audit logs', function (): void {
    [$organization, $owner] = createProjectApiFixture();

    $createResponse = $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/projects", [
            'name' => 'Marketing Site',
            'description' => 'Public website',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Marketing Site');

    $projectId = (string) $createResponse->json('data.id');

    expect(AuditLog::query()->where('operation', 'project.created')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->patchJson("/api/v1/projects/{$projectId}", [
            'name' => 'Marketing Platform',
            'description' => 'Updated scope',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Marketing Platform');

    expect(AuditLog::query()->where('operation', 'project.updated')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/projects/{$projectId}")
        ->assertNoContent();

    expect(AuditLog::query()->where('operation', 'project.deleted')->exists())->toBeTrue();
    expect(Project::query()->whereKey($projectId)->exists())->toBeFalse();
});

it('forbids cross organization project access', function (): void {
    [$firstOrg, $firstOwner] = createProjectApiFixture();
    [$secondOrg] = createProjectApiFixture();

    $project = Project::query()->create([
        'organization_id' => (string) $secondOrg->getKey(),
        'name' => 'Private Project',
        'description' => null,
    ]);

    $this->actingAs($firstOwner)
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertForbidden();
});

it('prevents deleting a project that still has servers', function (): void {
    [$organization, $owner] = createProjectApiFixture();

    $project = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Attached Project',
        'description' => null,
    ]);

    Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $project->getKey(),
        'hostname' => 'attached.example.test',
        'ip_address' => '10.0.0.55',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['project']);
});

it('forbids viewers from creating projects', function (): void {
    [$organization, $viewer] = createProjectApiFixture(TeamRole::VIEWER);

    $this->actingAs($viewer)
        ->postJson("/api/v1/organizations/{$organization->id}/projects", [
            'name' => 'Blocked Project',
        ])
        ->assertForbidden();
});
