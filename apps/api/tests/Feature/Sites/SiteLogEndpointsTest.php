<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteLogType;
use App\Modules\Sites\Jobs\FetchSiteLogsJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('returns loading and dispatches fetch site logs job on cache miss', function (): void {
    Queue::fake();

    $organization = Organization::query()->create([
        'name' => 'Site Logs API Org',
        'slug' => 'site-logs-api-'.Str::random(6),
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
        'hostname' => 'site-logs-api.example.test',
        'ip_address' => '10.0.0.90',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $site = Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'site-logs.example.test',
        'webroot' => '/var/www/site-logs.example.test',
        'runtime' => Runtime::PHP->value,
        'deploy_branch' => 'main',
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/logs?type=nginx_access&lines=100")
        ->assertOk()
        ->assertJsonPath('data.status', 'loading')
        ->assertJsonPath('data.logType', 'nginx_access');

    Queue::assertPushed(FetchSiteLogsJob::class);

    expect(AuditLog::query()->where('operation', 'site.logs.viewed')->exists())->toBeTrue();
});

it('returns cached site logs when available', function (): void {
    Queue::fake();

    $organization = Organization::query()->create([
        'name' => 'Site Logs Cache Org',
        'slug' => 'site-logs-cache-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'site-logs-cache.example.test',
        'ip_address' => '10.0.0.91',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $site = Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'cached.example.test',
        'webroot' => '/var/www/cached.example.test',
        'runtime' => Runtime::PHP->value,
        'deploy_branch' => 'main',
        'status' => 'active',
    ]);

    Cache::put(
        FetchSiteLogsJob::cacheKey((string) $site->getKey(), SiteLogType::APPLICATION, 100),
        [
            'status' => 'ready',
            'lines' => ['[2026-06-13] local.ERROR: test'],
        ],
        now()->addMinutes(5),
    );

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/logs?type=application&lines=100")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.lines.0', '[2026-06-13] local.ERROR: test');

    Queue::assertNothingPushed();
});

it('rejects application logs for unsupported site runtime', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Site Logs Runtime Org',
        'slug' => 'site-logs-runtime-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'site-logs-runtime.example.test',
        'ip_address' => '10.0.0.92',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $site = Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'static-runtime.example.test',
        'webroot' => '/var/www/static-runtime.example.test',
        'runtime' => Runtime::STATIC->value,
        'deploy_branch' => 'main',
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/logs?type=application")
        ->assertUnprocessable();
});

it('forbids cross organization site log access', function (): void {
    $organizationA = Organization::query()->create([
        'name' => 'Site Logs Org A',
        'slug' => 'site-logs-org-a-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organizationB = Organization::query()->create([
        'name' => 'Site Logs Org B',
        'slug' => 'site-logs-org-b-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $ownerA = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organizationA->getKey(),
    ]);
    $organizationA->users()->attach($ownerA->getKey(), ['role' => TeamRole::OWNER->value]);

    $ownerB = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organizationB->getKey(),
    ]);
    $organizationB->users()->attach($ownerB->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organizationA->getKey(),
        'hostname' => 'site-logs-cross.example.test',
        'ip_address' => '10.0.0.93',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $ownerA->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $site = Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organizationA->getKey(),
        'domain' => 'cross.example.test',
        'webroot' => '/var/www/cross.example.test',
        'runtime' => Runtime::PHP->value,
        'deploy_branch' => 'main',
        'status' => 'active',
    ]);

    $this->actingAs($ownerB)
        ->getJson("/api/v1/sites/{$site->id}/logs?type=nginx_error")
        ->assertForbidden();
});

it('site logs ready event broadcasts on server logs channel', function (): void {
    $event = new \App\Modules\Sites\Events\SiteLogsReady(
        serverId: 'server-1',
        organizationId: 'org-1',
        siteId: 'site-1',
        logType: 'nginx_error',
        linesRequested: 100,
        status: 'ready',
        lines: ['error line'],
    );

    expect($event->broadcastAs())->toBe('site.logs.ready')
        ->and($event->broadcastOn()[0]->name)->toBe('private-server.server-1.logs')
        ->and($event->broadcastWith()['siteId'])->toBe('site-1');
});
