<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

it('updates build assets setting for sites', function (): void {
    [$site, $owner] = siteBuildAssetsSettingsFixture();

    $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}", [
            'buildAssets' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.buildAssets', false);

    expect($site->refresh()->build_assets)->toBeFalse();
});

it('defaults build assets to true for new sites', function (): void {
    [$site, $owner] = siteBuildAssetsSettingsFixture();

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}")
        ->assertOk()
        ->assertJsonPath('data.buildAssets', true);
});

/**
 * @return array{0: Site, 1: User}
 */
function siteBuildAssetsSettingsFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Build Assets Settings Org',
        'slug' => 'build-assets-settings-'.Str::random(6),
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
        'hostname' => 'build-assets-settings.test',
        'ip_address' => '10.0.0.85',
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
        'domain' => 'build-assets-'.Str::random(6).'.test',
        'aliases' => [],
        'webroot' => '/var/www/build-assets.test/current/public',
        'runtime' => Runtime::PHP,
        'deploy_mode' => DeployMode::GIT,
        'repository_url' => 'git@github.com:helix/example.git',
        'repository_provider' => 'github',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'build_assets' => true,
        'status' => SiteStatus::ACTIVE,
        'auto_deploy_enabled' => false,
    ]);

    return [$site, $owner];
}
