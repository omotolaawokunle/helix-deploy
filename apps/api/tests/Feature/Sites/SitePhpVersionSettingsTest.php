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

it('updates php version for php runtime sites', function (): void {
    [$site, $owner] = sitePhpVersionSettingsFixture();

    $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}", [
            'phpVersion' => '8.4',
        ])
        ->assertOk()
        ->assertJsonPath('data.phpVersion', '8.4');

    expect($site->refresh()->php_version)->toBe('8.4');
});

it('rejects unsupported php versions', function (): void {
    [$site, $owner] = sitePhpVersionSettingsFixture();

    $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}", [
            'phpVersion' => '8.5',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phpVersion']);

    expect($site->refresh()->php_version)->toBe('8.3');
});

/**
 * @return array{0: Site, 1: User}
 */
function sitePhpVersionSettingsFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'PHP Version Settings Org',
        'slug' => 'php-version-settings-'.Str::random(6),
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
        'hostname' => 'php-version-settings.test',
        'ip_address' => '10.0.0.84',
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
        'domain' => 'php-version-settings.example.test',
        'aliases' => [],
        'webroot' => '/var/www/php-version-settings.example.test/current/public',
        'runtime' => Runtime::PHP,
        'deploy_mode' => DeployMode::GIT,
        'php_version' => '8.3',
        'repository_url' => 'git@github.com:helix/example.git',
        'repository_provider' => 'github',
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE,
        'auto_deploy_enabled' => false,
    ]);

    return [$site, $owner];
}
