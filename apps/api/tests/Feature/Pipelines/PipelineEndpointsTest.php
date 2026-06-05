<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

/**
 * @return array{Organization, User}
 */
function createPipelineApiFixture(TeamRole $role = TeamRole::OWNER): array
{
    $organization = Organization::query()->create([
        'name' => 'Pipelines API Org',
        'slug' => 'pipelines-api-'.Str::random(6),
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

it('lists pipelines for the current organization', function (): void {
    [$organization, $owner] = createPipelineApiFixture();

    Pipeline::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Production Deploy',
        'description' => 'Full release flow',
        'stages' => [],
        'created_by' => (string) $owner->getKey(),
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/pipelines")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Production Deploy');
});

it('creates updates and deletes a pipeline with steps and audit logs', function (): void {
    [$organization, $owner] = createPipelineApiFixture();

    $createResponse = $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/pipelines", [
            'name' => 'Release Pipeline',
            'description' => 'Migrate then deploy',
            'steps' => [
                [
                    'name' => 'Run migrations',
                    'type' => PipelineStepType::MIGRATE->value,
                    'order' => 0,
                    'config' => [],
                    'requiresApproval' => false,
                    'retryAttempts' => 1,
                ],
                [
                    'name' => 'Deploy release',
                    'type' => PipelineStepType::DEPLOY->value,
                    'order' => 1,
                    'config' => [],
                    'requiresApproval' => false,
                    'retryAttempts' => 0,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Release Pipeline')
        ->assertJsonCount(2, 'data.steps');

    $pipelineId = (string) $createResponse->json('data.id');
    $firstStepId = (string) $createResponse->json('data.steps.0.id');

    expect(AuditLog::query()->where('operation', 'pipeline.created')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->patchJson("/api/v1/pipelines/{$pipelineId}", [
            'name' => 'Release Pipeline v2',
            'steps' => [
                [
                    'id' => $firstStepId,
                    'name' => 'Run migrations',
                    'type' => PipelineStepType::MIGRATE->value,
                    'order' => 0,
                    'config' => ['environment' => 'production'],
                    'requiresApproval' => false,
                    'retryAttempts' => 2,
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Release Pipeline v2')
        ->assertJsonCount(1, 'data.steps');

    expect(AuditLog::query()->where('operation', 'pipeline.updated')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/pipelines/{$pipelineId}")
        ->assertNoContent();

    expect(AuditLog::query()->where('operation', 'pipeline.deleted')->exists())->toBeTrue();
    expect(Pipeline::query()->whereKey($pipelineId)->exists())->toBeFalse();
});

it('forbids cross organization pipeline access', function (): void {
    [$firstOrg, $firstOwner] = createPipelineApiFixture();
    [$secondOrg, $secondOwner] = createPipelineApiFixture();

    $pipeline = Pipeline::query()->create([
        'organization_id' => (string) $secondOrg->getKey(),
        'name' => 'Private Pipeline',
        'description' => null,
        'stages' => [],
        'created_by' => (string) $secondOwner->getKey(),
    ]);

    $this->actingAs($firstOwner)
        ->getJson("/api/v1/pipelines/{$pipeline->id}")
        ->assertForbidden();
});

it('prevents deleting a pipeline linked to sites', function (): void {
    [$organization, $owner] = createPipelineApiFixture();

    $pipeline = Pipeline::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Linked Pipeline',
        'description' => null,
        'stages' => [],
        'created_by' => (string) $owner->getKey(),
    ]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'pipeline-site.example.test',
        'ip_address' => '10.0.0.40',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'pipeline_id' => (string) $pipeline->getKey(),
        'domain' => 'pipeline-site.example.test',
        'aliases' => [],
        'webroot' => '/var/www/pipeline-site.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => 'git',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/pipelines/{$pipeline->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pipeline']);
});

it('rejects invalid project id on create', function (): void {
    [$organization, $owner] = createPipelineApiFixture();
    [$otherOrg] = createPipelineApiFixture();

    $foreignProject = Project::query()->create([
        'organization_id' => (string) $otherOrg->getKey(),
        'name' => 'Foreign',
        'description' => null,
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/pipelines", [
            'name' => 'Scoped Pipeline',
            'projectId' => (string) $foreignProject->getKey(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['projectId']);
});

it('forbids developers from creating pipelines', function (): void {
    [$organization, $developer] = createPipelineApiFixture(TeamRole::DEVELOPER);

    $this->actingAs($developer)
        ->postJson("/api/v1/organizations/{$organization->id}/pipelines", [
            'name' => 'Dev Pipeline',
        ])
        ->assertForbidden();
});
