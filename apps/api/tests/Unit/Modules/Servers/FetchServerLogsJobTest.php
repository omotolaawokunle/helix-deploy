<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Monitoring\Contracts\RemoteLogReaderInterface;
use App\Modules\Monitoring\Services\RemoteLogReader;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Enums\ServerLogType;
use App\Modules\Servers\Events\ServerLogsReady;
use App\Modules\Servers\Jobs\FetchServerLogsJob;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Services\ServerLogPathResolver;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('fetch server logs job caches ready lines and broadcasts event', function (): void {
    Event::fake([ServerLogsReady::class]);

    $organization = Organization::query()->create([
        'name' => 'Server Logs Job Org',
        'slug' => 'server-logs-job-'.Str::random(6),
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
        'hostname' => 'logs.example.test',
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

    $fake = (new FakeSSHConnection())
        ->connect()
        ->addResponse('tail -n 100*', new SSHResult('tail', 0, "127.0.0.1 GET /\n", '', 0.01));

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);

    $job = new FetchServerLogsJob(
        serverId: (string) $server->getKey(),
        logType: ServerLogType::NGINX_ACCESS,
        lines: 100,
    );

    $job->handle(
        $manager,
        new ServerLogPathResolver(),
        new RemoteLogReader(),
        app(CredentialVault::class),
    );

    $cacheKey = FetchServerLogsJob::cacheKey((string) $server->getKey(), ServerLogType::NGINX_ACCESS, 100);
    $cached = Cache::get($cacheKey);

    expect($cached['status'])->toBe('ready')
        ->and($cached['lines'])->toBe(['127.0.0.1 GET /']);

    Event::assertDispatched(ServerLogsReady::class);
});

it('fetch server logs job caches failure without leaking details', function (): void {
    Event::fake([ServerLogsReady::class]);

    $organization = Organization::query()->create([
        'name' => 'Server Logs Fail Org',
        'slug' => 'server-logs-fail-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $owner = User::factory()->create();
    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'logs-fail.example.test',
        'ip_address' => '10.0.0.61',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andThrow(new RuntimeException('ssh failed'));

    $job = new FetchServerLogsJob(
        serverId: (string) $server->getKey(),
        logType: ServerLogType::NGINX_ERROR,
        lines: 50,
    );

    $job->handle(
        $manager,
        new ServerLogPathResolver(),
        app(RemoteLogReaderInterface::class),
        app(CredentialVault::class),
    );

    $cacheKey = FetchServerLogsJob::cacheKey((string) $server->getKey(), ServerLogType::NGINX_ERROR, 50);
    $cached = Cache::get($cacheKey);

    expect($cached['status'])->toBe('failed')
        ->and($cached['message'])->toBe('Unable to fetch server logs.');
});
