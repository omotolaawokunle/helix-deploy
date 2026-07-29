<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

it('enables auto deploy and returns webhook secret once', function (): void {
    [$site, $owner] = autoDeploySiteSettingsFixture();

    $response = $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}", [
            'autoDeployEnabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.autoDeployEnabled', true)
        ->assertJsonStructure(['webhookSecret', 'data' => ['webhookUrl', 'hasWebhookSecret']]);

    expect($response->json('webhookSecret'))->not->toBeEmpty();
    expect($response->json('data.hasWebhookSecret'))->toBeTrue();

    expect(AuditLog::query()->where('operation', 'site.auto_deploy_enabled')->exists())->toBeTrue();
});

it('disables auto deploy without removing webhook token', function (): void {
    [$site, $owner] = autoDeploySiteSettingsFixture(autoDeployEnabled: true);

    $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}", [
            'autoDeployEnabled' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.autoDeployEnabled', false)
        ->assertJsonPath('data.webhookUrl', fn (?string $url): bool => $url !== null && $url !== '');
});

it('rejects auto deploy enable for docker pull mode sites', function (): void {
    [$site, $owner] = autoDeploySiteSettingsFixture(
        deployMode: DeployMode::DOCKER,
        dockerBuildMode: DockerBuildMode::PULL,
    );

    $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}", [
            'autoDeployEnabled' => true,
        ])
        ->assertStatus(422);
});

it('enables auto deploy for docker build mode sites with repository', function (): void {
    [$site, $owner] = autoDeploySiteSettingsFixture(
        deployMode: DeployMode::DOCKER,
        dockerBuildMode: DockerBuildMode::BUILD,
    );

    $response = $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}", [
            'autoDeployEnabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.autoDeployEnabled', true)
        ->assertJsonStructure(['webhookSecret', 'data' => ['webhookUrl', 'hasWebhookSecret']]);

    expect($response->json('webhookSecret'))->not->toBeEmpty();
    expect($response->json('data.hasWebhookSecret'))->toBeTrue();
});

it('rejects auto deploy enable for docker build mode sites without repository', function (): void {
    [$site, $owner] = autoDeploySiteSettingsFixture(
        deployMode: DeployMode::DOCKER,
        dockerBuildMode: DockerBuildMode::BUILD,
        repositoryUrl: '',
    );

    $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}", [
            'autoDeployEnabled' => true,
        ])
        ->assertStatus(422);
});

it('rotates webhook secret for enabled auto deploy site', function (): void {
    [$site, $owner] = autoDeploySiteSettingsFixture(autoDeployEnabled: true);

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/webhook-secret/rotate")
        ->assertOk()
        ->assertJsonStructure(['data' => ['webhookSecret', 'webhookUrl']]);

    expect($response->json('data.webhookSecret'))->not->toBeEmpty();

    expect(AuditLog::query()->where('operation', 'site.webhook_secret_rotated')->exists())->toBeTrue();
});

it('forbids cross organization site auto deploy update', function (): void {
    [$site] = autoDeploySiteSettingsFixture();

    $otherOrg = Organization::query()->create([
        'name' => 'Other Auto Deploy Org',
        'slug' => 'other-auto-deploy-'.Str::random(6),
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
        ->patchJson("/api/v1/sites/{$site->id}", [
            'autoDeployEnabled' => true,
        ])
        ->assertForbidden();
});

/**
 * @return array{0: Site, 1: User}
 */
function autoDeploySiteSettingsFixture(
    bool $autoDeployEnabled = false,
    DeployMode $deployMode = DeployMode::GIT,
    ?DockerBuildMode $dockerBuildMode = null,
    ?string $repositoryUrl = null,
): array {
    $organization = Organization::query()->create([
        'name' => 'Auto Deploy Settings Org',
        'slug' => 'auto-deploy-settings-'.Str::random(6),
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
        'hostname' => 'auto-deploy-settings.test',
        'ip_address' => '10.0.0.30',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $resolvedRepositoryUrl = $repositoryUrl;

    if ($resolvedRepositoryUrl === null) {
        $resolvedRepositoryUrl = $deployMode === DeployMode::GIT || $dockerBuildMode === DockerBuildMode::BUILD
            ? 'git@github.com:helix/example.git'
            : null;
    }

    $site = Site::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'auto-deploy-settings.example.test',
        'aliases' => [],
        'webroot' => '/var/www/auto-deploy-settings.example.test/current/public',
        'runtime' => $deployMode === DeployMode::DOCKER ? Runtime::DOCKER : Runtime::PHP,
        'deploy_mode' => $deployMode,
        'docker_build_mode' => $dockerBuildMode?->value,
        'repository_url' => $resolvedRepositoryUrl !== '' ? $resolvedRepositoryUrl : null,
        'repository_provider' => $resolvedRepositoryUrl !== null && $resolvedRepositoryUrl !== '' ? 'github' : null,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE,
        'auto_deploy_enabled' => $autoDeployEnabled,
        'webhook_token' => $autoDeployEnabled ? bin2hex(random_bytes(16)) : null,
    ]);

    if ($autoDeployEnabled) {
        app(\App\Modules\Sites\Services\SiteWebhookSecretService::class)
            ->encryptAndStore($site, $organization, 'existing-secret');
        $site->refresh();
    }

    return [$site, $owner];
}
