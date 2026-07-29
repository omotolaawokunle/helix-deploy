<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteWebhookSecretService;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

it('updates deploy mode and docker build mode for docker runtime sites', function (): void {
    [$site, $owner] = siteDeployModeSettingsFixture(
        runtime: Runtime::DOCKER,
        deployMode: DeployMode::GIT,
    );

    $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}", [
            'deployMode' => DeployMode::DOCKER->value,
            'dockerBuildMode' => DockerBuildMode::BUILD->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.deployMode', DeployMode::DOCKER->value)
        ->assertJsonPath('data.dockerBuildMode', DockerBuildMode::BUILD->value);

    expect($site->refresh()->deploy_mode)->toBe(DeployMode::DOCKER)
        ->and($site->docker_build_mode)->toBe(DockerBuildMode::BUILD);
});

it('clears docker build mode when switching to git deploy mode', function (): void {
    [$site, $owner] = siteDeployModeSettingsFixture(
        runtime: Runtime::DOCKER,
        deployMode: DeployMode::DOCKER,
        dockerBuildMode: DockerBuildMode::BUILD,
    );

    $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}", [
            'deployMode' => DeployMode::GIT->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.deployMode', DeployMode::GIT->value)
        ->assertJsonPath('data.dockerBuildMode', null);

    expect($site->refresh()->deploy_mode)->toBe(DeployMode::GIT)
        ->and($site->docker_build_mode)->toBeNull();
});

it('rejects deploy mode change that leaves auto deploy enabled but ineligible', function (): void {
    [$site, $owner] = siteDeployModeSettingsFixture(
        runtime: Runtime::DOCKER,
        deployMode: DeployMode::DOCKER,
        dockerBuildMode: DockerBuildMode::BUILD,
        autoDeployEnabled: true,
    );

    $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}", [
            'deployMode' => DeployMode::DOCKER->value,
            'dockerBuildMode' => DockerBuildMode::PULL->value,
        ])
        ->assertStatus(422);
});

/**
 * @return array{0: Site, 1: User}
 */
function siteDeployModeSettingsFixture(
    Runtime $runtime = Runtime::PHP,
    DeployMode $deployMode = DeployMode::GIT,
    ?DockerBuildMode $dockerBuildMode = null,
    bool $autoDeployEnabled = false,
): array {
    $organization = Organization::query()->create([
        'name' => 'Deploy Mode Settings Org',
        'slug' => 'deploy-mode-settings-'.Str::random(6),
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
        'hostname' => 'deploy-mode-settings.test',
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

    $site = Site::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'deploy-mode-settings.example.test',
        'aliases' => [],
        'webroot' => '/var/www/deploy-mode-settings.example.test/current/public',
        'runtime' => $runtime,
        'deploy_mode' => $deployMode,
        'docker_build_mode' => $dockerBuildMode?->value,
        'docker_compose_path' => $runtime === Runtime::DOCKER ? 'docker-compose.yml' : null,
        'repository_url' => 'git@github.com:helix/example.git',
        'repository_provider' => 'github',
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE,
        'auto_deploy_enabled' => $autoDeployEnabled,
        'webhook_token' => $autoDeployEnabled ? bin2hex(random_bytes(16)) : null,
    ]);

    if ($autoDeployEnabled) {
        app(SiteWebhookSecretService::class)
            ->encryptAndStore($site, $organization, 'existing-secret');
        $site->refresh();
    }

    return [$site, $owner];
}
