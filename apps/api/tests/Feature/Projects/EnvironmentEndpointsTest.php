<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;

it('manages environments for a project', function (): void {
    [$organization, $owner] = createProjectApiFixture();

    $project = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Core App',
        'description' => null,
    ]);

    $createResponse = $this->actingAs($owner)
        ->postJson("/api/v1/projects/{$project->id}/environments", [
            'name' => 'staging',
            'label' => 'Staging',
            'isProduction' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'staging')
        ->assertJsonPath('data.isProduction', false);

    $environmentId = (string) $createResponse->json('data.id');

    expect(AuditLog::query()->where('operation', 'environment.created')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->getJson("/api/v1/projects/{$project->id}/environments")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($owner)
        ->patchJson("/api/v1/projects/{$project->id}/environments/{$environmentId}", [
            'name' => 'production',
            'label' => 'Production',
            'isProduction' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'production')
        ->assertJsonPath('data.isProduction', true);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/projects/{$project->id}/environments/{$environmentId}")
        ->assertNoContent();

    expect(Environment::query()->whereKey($environmentId)->exists())->toBeFalse();
});

it('forbids cross organization environment access', function (): void {
    [$firstOrg, $firstOwner] = createProjectApiFixture();
    [$secondOrg] = createProjectApiFixture();

    $project = Project::query()->create([
        'organization_id' => (string) $secondOrg->getKey(),
        'name' => 'Other Org Project',
        'description' => null,
    ]);

    $environment = Environment::query()->create([
        'organization_id' => (string) $secondOrg->getKey(),
        'project_id' => (string) $project->getKey(),
        'name' => 'staging',
        'label' => 'Staging',
        'is_production' => false,
    ]);

    $this->actingAs($firstOwner)
        ->getJson("/api/v1/environments/{$environment->id}")
        ->assertForbidden();
});

it('prevents deleting an environment that still has servers', function (): void {
    [$organization, $owner] = createProjectApiFixture();

    $project = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Locked Env Project',
        'description' => null,
    ]);

    $environment = Environment::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $project->getKey(),
        'name' => 'staging',
        'label' => 'Staging',
        'is_production' => false,
    ]);

    Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $project->getKey(),
        'environment_id' => (string) $environment->getKey(),
        'hostname' => 'env-lock.example.test',
        'ip_address' => '10.0.0.56',
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
        ->deleteJson("/api/v1/projects/{$project->id}/environments/{$environment->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['environment']);
});

it('forbids developers from creating environments', function (): void {
    [$organization, $developer] = createProjectApiFixture(TeamRole::DEVELOPER);

    $project = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Dev Restricted',
        'description' => null,
    ]);

    $this->actingAs($developer)
        ->postJson("/api/v1/projects/{$project->id}/environments", [
            'name' => 'staging',
            'isProduction' => false,
        ])
        ->assertForbidden();
});
