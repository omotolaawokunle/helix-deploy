<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\DTOs\DiscoveredSiteSnapshot;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Actions\ImportDiscoveredSitesAction;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;

it('imports discovered sites and skips managed active sites', function (): void {
    [$organization, $owner, $server] = createImportDiscoveredSitesFixture();

    $action = app(ImportDiscoveredSitesAction::class);

    $result = $action->import($server, [
        new DiscoveredSiteSnapshot('app.example.com', '/var/www/app/public', 'php'),
        new DiscoveredSiteSnapshot('api.example.com', '/var/www/api/public', 'nodejs'),
    ]);

    expect($result)->toBe(['created' => 2, 'updated' => 0]);

    $discovered = Site::query()
        ->where('server_id', (string) $server->getKey())
        ->where('status', SiteStatus::DISCOVERED->value)
        ->get();

    expect($discovered)->toHaveCount(2);

    Site::query()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'existing.example.com',
        'aliases' => [],
        'webroot' => '/var/www/existing',
        'runtime' => 'php',
        'deploy_mode' => 'git',
        'deploy_branch' => 'main',
        'status' => SiteStatus::ACTIVE->value,
    ]);

    $secondPass = $action->import($server, [
        new DiscoveredSiteSnapshot('existing.example.com', '/var/www/new-path', 'php'),
        new DiscoveredSiteSnapshot('app.example.com', '/var/www/app/public', 'php'),
    ]);

    expect($secondPass)->toBe(['created' => 0, 'updated' => 1]);
    expect(AuditLog::query()->where('operation', 'server.sites_discovered')->exists())->toBeTrue();
});

/**
 * @return array{0: Organization, 1: User, 2: Server}
 */
function createImportDiscoveredSitesFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Import Sites Org',
        'slug' => 'import-sites-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'import.example.test',
        'ip_address' => '127.0.0.1',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => ServerStatus::ACTIVE->value,
        'management_mode' => 'observe',
        'php_version' => 'PHP 8.3.1 (cli)',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    return [$organization, $owner, $server];
}
