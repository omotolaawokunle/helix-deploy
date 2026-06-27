<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Jobs\BrowseSiteDatabaseJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('queues site database table browse', function (): void {
    Queue::fake();

    [$site, $owner] = siteDatabaseApiFixture();

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/database/tables")
        ->assertOk()
        ->assertJsonPath('data.status', 'loading')
        ->assertJsonPath('data.kind', 'tables');

    Queue::assertPushed(BrowseSiteDatabaseJob::class);
});

it('returns cached site database tables', function (): void {
    [$site, $owner] = siteDatabaseApiFixture();

    $cacheKey = BrowseSiteDatabaseJob::cacheKey(
        (string) $site->getKey(),
        \App\Packages\DatabaseBrowser\Enums\DatabaseBrowseKind::TABLES,
        null,
        null,
    );

    Cache::put($cacheKey, [
        'status' => 'ready',
        'database' => 'app_production',
        'engine' => 'postgresql',
        'tables' => ['users', 'posts'],
    ], now()->addMinutes(5));

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/database/tables")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonCount(2, 'data.tables');
});

/**
 * @return array{0: Site, 1: User}
 */
function siteDatabaseApiFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Site DB Browser Org',
        'slug' => 'site-db-browser-'.Str::random(6),
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
        'hostname' => 'site-db.test',
        'ip_address' => '10.0.0.41',
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
        'domain' => 'site-db.example.test',
        'aliases' => [],
        'webroot' => '/var/www/site-db.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => DeployMode::GIT->value,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE->value,
    ]);

    $vault = app(CredentialVaultInterface::class);
    $vault->storeSecret($organization, $site, 'DB_HOST', '127.0.0.1');
    $vault->storeSecret($organization, $site, 'DB_DATABASE', 'app_production');
    $vault->storeSecret($organization, $site, 'DB_USERNAME', 'deploy');
    $vault->storeSecret($organization, $site, 'DB_PASSWORD', 'secret');
    $vault->storeSecret($organization, $site, 'DB_CONNECTION', 'pgsql');

    return [$site, $owner];
}
