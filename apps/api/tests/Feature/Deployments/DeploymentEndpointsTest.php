<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Deployments\Events\DeploymentStarted;
use App\Modules\Deployments\Jobs\RunDeploymentJob;
use App\Modules\Deployments\Jobs\RunRollbackJob;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('dispatches deployment job and returns accepted response', function (): void {
    Queue::fake();
    Event::fake([DeploymentStarted::class]);

    [$site, $owner] = deploymentSiteFixture();

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/deployments")
        ->assertAccepted()
        ->assertJsonPath('data.status', DeploymentStatus::PENDING->value)
        ->assertJsonPath('data.siteId', (string) $site->getKey())
        ->assertJsonStructure(['data' => ['id', 'status']]);

    $deploymentId = $response->json('data.id');
    $response->assertJsonPath('channel', 'deployment.'.$deploymentId);

    Queue::assertPushed(RunDeploymentJob::class, function (RunDeploymentJob $job) use ($deploymentId): bool {
        return $job->deploymentId === $deploymentId;
    });

    expect(AuditLog::query()->where('operation', 'deployment.triggered')->exists())->toBeTrue();
    expect(Deployment::query()->where('site_id', $site->getKey())->count())->toBe(1);
});

it('returns conflict when deployment already in progress', function (): void {
    [$site, $owner] = deploymentSiteFixture();

    Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $site->organization_id,
        'type' => DeploymentType::DEPLOY,
        'status' => DeploymentStatus::RUNNING,
        'trigger_type' => TriggerType::MANUAL,
        'branch' => 'main',
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/deployments")
        ->assertStatus(409);
});

it('returns forbidden for cross org site deployment', function (): void {
    [$site] = deploymentSiteFixture();

    $otherOrg = Organization::query()->create([
        'name' => 'Other Org',
        'slug' => 'other-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $otherOrg->generateAndStoreMasterKey();

    $intruder = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $otherOrg->getKey(),
    ]);
    $otherOrg->users()->attach($intruder->getKey(), ['role' => TeamRole::OWNER->value]);

    $this->actingAs($intruder)
        ->postJson("/api/v1/sites/{$site->id}/deployments")
        ->assertForbidden();
});

it('returns validation error when production rollback has no reason', function (): void {
    [$site, $owner, $deployment] = rollbackApiFixture(isProduction: true);

    $this->actingAs($owner)
        ->postJson("/api/v1/deployments/{$deployment->id}/rollback", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('accepts production rollback with reason and dispatches job', function (): void {
    Queue::fake();

    [$site, $owner, $deployment] = rollbackApiFixture(isProduction: true);

    $fake = (new \App\Packages\SSH\FakeSSHConnection())->connect();
    $fake->addSequence('test -d *', sshSuccess('exists'));

    $this->mock(\App\Packages\SSH\SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/deployments/{$deployment->id}/rollback", [
            'reason' => 'Critical regression detected in checkout flow',
        ])
        ->assertAccepted()
        ->assertJsonPath('data.type', DeploymentType::ROLLBACK->value);

    $rollbackId = $response->json('data.id');

    Queue::assertPushed(RunRollbackJob::class, fn (RunRollbackJob $job): bool => $job->deploymentId === $rollbackId);

    $rollback = Deployment::query()->withoutGlobalScope('owned_by_organization')->find($rollbackId);
    expect($rollback?->rollback_reason)->toBe('Critical regression detected in checkout flow');
});

it('returns not found when release directory is missing on server', function (): void {
    [$site, $owner, $deployment] = rollbackApiFixture();

    $fake = (new \App\Packages\SSH\FakeSSHConnection())->connect();
    $fake->addSequence('test -d *', sshSuccess('missing'));

    $this->mock(\App\Packages\SSH\SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    $this->actingAs($owner)
        ->postJson("/api/v1/deployments/{$deployment->id}/rollback")
        ->assertNotFound();
});

it('returns unprocessable when server is in observe mode', function (): void {
    [$site, $owner, $deployment, $server] = rollbackApiFixture();
    $server->forceFill(['management_mode' => 'observe'])->save();

    $this->mock(\App\Packages\SSH\SSHManager::class, function ($mock): void {
        $mock->shouldNotReceive('connect');
    });

    $this->actingAs($owner)
        ->postJson("/api/v1/deployments/{$deployment->id}/rollback")
        ->assertUnprocessable();
});

it('shows deployment for authorized org member', function (): void {
    [$site, $owner] = deploymentSiteFixture();

    $deployment = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $site->organization_id,
        'type' => DeploymentType::DEPLOY,
        'status' => DeploymentStatus::SUCCESS,
        'trigger_type' => TriggerType::MANUAL,
        'branch' => 'main',
        'finished_at' => now(),
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/deployments/{$deployment->id}")
        ->assertOk()
        ->assertJsonPath('data.id', (string) $deployment->getKey());
});

/**
 * @return array{0: Site, 1: User}
 */
function deploymentSiteFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Deployments API Org',
        'slug' => 'deployments-api-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'deploy-api.test',
        'ip_address' => '10.0.0.10',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $site = Site::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'deploy-api.example.test',
        'aliases' => [],
        'webroot' => '/var/www/deploy-api.example.test/current/public',
        'runtime' => Runtime::PHP,
        'deploy_mode' => DeployMode::GIT,
        'repository_url' => 'git@github.com:helix/example.git',
        'repository_provider' => 'github',
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE,
    ]);

    return [$site, $owner];
}

/**
 * @return array{0: Site, 1: User, 2: Deployment, 3?: Server}
 */
function rollbackApiFixture(bool $isProduction = false): array
{
    [$site, $owner] = deploymentSiteFixture();
    $server = Server::query()->withoutGlobalScope('owned_by_organization')->findOrFail($site->server_id);

    if ($isProduction) {
        $project = Project::query()->create([
            'organization_id' => (string) $site->organization_id,
            'name' => 'Rollback API Project',
        ]);
        $environment = Environment::query()->create([
            'project_id' => (string) $project->getKey(),
            'organization_id' => (string) $site->organization_id,
            'name' => 'production',
            'label' => 'Production',
            'is_production' => true,
        ]);
        $site->forceFill(['environment_id' => (string) $environment->getKey()])->save();
    }

    $releasePath = '/var/www/'.$site->domain.'/releases/previous';

    $deployment = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $site->organization_id,
        'type' => DeploymentType::DEPLOY,
        'status' => DeploymentStatus::SUCCESS,
        'trigger_type' => TriggerType::MANUAL,
        'triggered_by' => (string) $owner->getKey(),
        'branch' => 'main',
        'release_path' => $releasePath,
        'finished_at' => now(),
    ]);

    $credential = \App\Modules\Credentials\Models\Credential::query()->create([
        'organization_id' => (string) $site->organization_id,
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'ssh_private_key',
        'name' => 'Rollback API Key',
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => 'fp',
        'created_by' => (string) $owner->getKey(),
        'last_used_at' => null,
    ]);
    $server->forceFill(['credential_id' => (string) $credential->getKey()])->save();

    return [$site, $owner, $deployment, $server];
}
