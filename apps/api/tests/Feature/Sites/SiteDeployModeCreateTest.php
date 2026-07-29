<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Jobs\CreateSiteJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('creates a docker site with deploy mode and docker build mode', function (): void {
    Queue::fake();

    [$server, $owner] = siteDeployModeCreateFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/sites", [
            'domain' => 'docker-site.example.test',
            'runtime' => Runtime::DOCKER->value,
            'deployMode' => DeployMode::DOCKER->value,
            'dockerBuildMode' => DockerBuildMode::BUILD->value,
            'appPort' => 3000,
            'repositoryUrl' => 'https://github.com/org/repo.git',
            'deployBranch' => 'main',
        ])
        ->assertAccepted()
        ->assertJsonPath('data.runtime', Runtime::DOCKER->value)
        ->assertJsonPath('data.deployMode', DeployMode::DOCKER->value)
        ->assertJsonPath('data.dockerBuildMode', DockerBuildMode::BUILD->value);

    $site = Site::query()
        ->withoutGlobalScope('owned_by_organization')
        ->where('domain', 'docker-site.example.test')
        ->first();

    expect($site)->not->toBeNull()
        ->and($site?->runtime)->toBe(Runtime::DOCKER)
        ->and($site?->deploy_mode)->toBe(DeployMode::DOCKER)
        ->and($site?->docker_build_mode)->toBe(DockerBuildMode::BUILD);

    Queue::assertPushedOn('provisioning', CreateSiteJob::class);
});

it('defaults deploy mode to docker when runtime is docker and deploy mode is omitted', function (): void {
    Queue::fake();

    [$server, $owner] = siteDeployModeCreateFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/sites", [
            'domain' => 'docker-default.example.test',
            'runtime' => Runtime::DOCKER->value,
            'appPort' => 8080,
        ])
        ->assertAccepted()
        ->assertJsonPath('data.deployMode', DeployMode::DOCKER->value)
        ->assertJsonPath('data.dockerBuildMode', DockerBuildMode::BUILD->value);

    $site = Site::query()
        ->withoutGlobalScope('owned_by_organization')
        ->where('domain', 'docker-default.example.test')
        ->first();

    expect($site?->deploy_mode)->toBe(DeployMode::DOCKER)
        ->and($site?->docker_build_mode)->toBe(DockerBuildMode::BUILD);
});

it('rejects docker deploy mode with a non-docker runtime on create', function (): void {
    [$server, $owner] = siteDeployModeCreateFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/sites", [
            'domain' => 'invalid-deploy-mode.example.test',
            'runtime' => Runtime::PHP->value,
            'deployMode' => DeployMode::DOCKER->value,
            'phpVersion' => '8.3',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['deployMode']);
});

it('rejects docker deploy mode update on non-docker runtime sites', function (): void {
    [$site, $owner] = siteDeployModeCreateSiteFixture(
        runtime: Runtime::PHP,
        deployMode: DeployMode::GIT,
    );

    $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}", [
            'deployMode' => DeployMode::DOCKER->value,
            'dockerBuildMode' => DockerBuildMode::BUILD->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['deployMode']);
});

/**
 * @return array{0: Server, 1: User}
 */
function siteDeployModeCreateFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Deploy Mode Create Org',
        'slug' => 'deploy-mode-create-'.Str::random(6),
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
        'hostname' => 'deploy-mode-create.test',
        'ip_address' => '10.0.0.60',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    return [$server, $owner];
}

/**
 * @return array{0: Site, 1: User}
 */
function siteDeployModeCreateSiteFixture(
    Runtime $runtime = Runtime::PHP,
    DeployMode $deployMode = DeployMode::GIT,
): array {
    [$server, $owner] = siteDeployModeCreateFixture();

    $site = Site::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $server->organization_id,
        'domain' => 'deploy-mode-update.example.test',
        'aliases' => [],
        'webroot' => '/var/www/deploy-mode-update.example.test/current/public',
        'runtime' => $runtime,
        'deploy_mode' => $deployMode,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE,
    ]);

    return [$site, $owner];
}
