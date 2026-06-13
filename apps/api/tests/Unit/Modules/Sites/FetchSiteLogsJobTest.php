<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Events\SiteLogsReady;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteLogType;
use App\Modules\Sites\Jobs\FetchSiteLogsJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteLogPathResolver;
use App\Modules\Monitoring\Services\RemoteLogReader;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('fetch site logs job caches ready lines and broadcasts event', function (): void {
    Event::fake([SiteLogsReady::class]);

    $organization = Organization::query()->create([
        'name' => 'Site Logs Job Org',
        'slug' => 'site-logs-job-'.Str::random(6),
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
        'hostname' => 'site-logs.example.test',
        'ip_address' => '10.0.0.70',
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
        'domain' => 'app.example.test',
        'webroot' => '/var/www/app.example.test',
        'runtime' => Runtime::PHP->value,
        'deploy_branch' => 'main',
        'status' => 'active',
    ]);

    $fake = (new FakeSSHConnection())
        ->connect()
        ->addResponse('tail -n 100*', new SSHResult('tail', 0, "2026/06/13 error\n", '', 0.01));

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);

    $job = new FetchSiteLogsJob(
        siteId: (string) $site->getKey(),
        logType: SiteLogType::NGINX_ERROR,
        lines: 100,
    );

    $job->handle(
        $manager,
        new SiteLogPathResolver(),
        new RemoteLogReader(),
        app(CredentialVault::class),
    );

    $cacheKey = FetchSiteLogsJob::cacheKey((string) $site->getKey(), SiteLogType::NGINX_ERROR, 100);
    $cached = Cache::get($cacheKey);

    expect($cached['status'])->toBe('ready')
        ->and($cached['lines'])->toBe(['2026/06/13 error']);

    Event::assertDispatched(SiteLogsReady::class);
});

it('fetch site application logs job uses latest laravel log file', function (): void {
    Event::fake([SiteLogsReady::class]);

    $organization = Organization::query()->create([
        'name' => 'Site App Logs Job Org',
        'slug' => 'site-app-logs-job-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $owner = User::factory()->create();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'site-app-logs.example.test',
        'ip_address' => '10.0.0.72',
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
        'domain' => 'app.example.test',
        'webroot' => '/var/www/app.example.test',
        'runtime' => Runtime::PHP->value,
        'deploy_branch' => 'main',
        'status' => 'active',
    ]);

    $fake = (new FakeSSHConnection())
        ->connect()
        ->addResponse('*laravel*.log*', new SSHResult('tail', 0, "[2026-06-13] local.ERROR: daily\n", '', 0.01));

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);

    $job = new FetchSiteLogsJob(
        siteId: (string) $site->getKey(),
        logType: SiteLogType::APPLICATION,
        lines: 100,
    );

    $job->handle(
        $manager,
        new SiteLogPathResolver(),
        new RemoteLogReader(),
        app(CredentialVault::class),
    );

    $cacheKey = FetchSiteLogsJob::cacheKey((string) $site->getKey(), SiteLogType::APPLICATION, 100);
    $cached = Cache::get($cacheKey);

    expect($cached['status'])->toBe('ready')
        ->and($cached['lines'])->toBe(['[2026-06-13] local.ERROR: daily']);

    Event::assertDispatched(SiteLogsReady::class);
});

it('fetch site logs job fails gracefully for unsupported application runtime', function (): void {
    Event::fake([SiteLogsReady::class]);

    $organization = Organization::query()->create([
        'name' => 'Site Logs Static Org',
        'slug' => 'site-logs-static-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $owner = User::factory()->create();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'static.example.test',
        'ip_address' => '10.0.0.71',
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
        'domain' => 'static.example.test',
        'webroot' => '/var/www/static.example.test',
        'runtime' => Runtime::STATIC->value,
        'deploy_branch' => 'main',
        'status' => 'active',
    ]);

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->never();

    $job = new FetchSiteLogsJob(
        siteId: (string) $site->getKey(),
        logType: SiteLogType::APPLICATION,
        lines: 100,
    );

    $job->handle(
        $manager,
        new SiteLogPathResolver(),
        new RemoteLogReader(),
        app(CredentialVault::class),
    );

    $cacheKey = FetchSiteLogsJob::cacheKey((string) $site->getKey(), SiteLogType::APPLICATION, 100);
    $cached = Cache::get($cacheKey);

    expect($cached['status'])->toBe('failed')
        ->and($cached['message'])->toContain('not available');

    Event::assertDispatched(SiteLogsReady::class);
});
