<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Enums\ServerLogType;
use App\Modules\Servers\Jobs\FetchServerLogsJob;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('returns loading and dispatches fetch server logs job on cache miss', function (): void {
    Queue::fake();

    $organization = Organization::query()->create([
        'name' => 'Server Logs API Org',
        'slug' => 'server-logs-api-'.Str::random(6),
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
        'hostname' => 'server-logs-api.example.test',
        'ip_address' => '10.0.0.80',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/logs?type=nginx_access&lines=100")
        ->assertOk()
        ->assertJsonPath('data.status', 'loading')
        ->assertJsonPath('data.logType', 'nginx_access')
        ->assertJsonPath('data.linesRequested', 100);

    Queue::assertPushed(FetchServerLogsJob::class);

    expect(AuditLog::query()->where('operation', 'server.logs.viewed')->exists())->toBeTrue();
});

it('returns cached server logs when available', function (): void {
    Queue::fake();

    $organization = Organization::query()->create([
        'name' => 'Server Logs Cache Org',
        'slug' => 'server-logs-cache-'.Str::random(6),
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
        'hostname' => 'server-logs-cache.example.test',
        'ip_address' => '10.0.0.81',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    Cache::put(
        FetchServerLogsJob::cacheKey((string) $server->getKey(), ServerLogType::NGINX_ERROR, 50),
        [
            'status' => 'ready',
            'lines' => ['error line'],
        ],
        now()->addMinutes(5),
    );

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/logs?type=nginx_error&lines=50")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.lines.0', 'error line');

    Queue::assertNothingPushed();
});

it('validates server log type query parameter', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Server Logs Validation Org',
        'slug' => 'server-logs-validation-'.Str::random(6),
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
        'hostname' => 'server-logs-validation.example.test',
        'ip_address' => '10.0.0.82',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/logs?type=invalid")
        ->assertUnprocessable();
});

it('forbids cross organization server log access', function (): void {
    $organizationA = Organization::query()->create([
        'name' => 'Server Logs Org A',
        'slug' => 'server-logs-org-a-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organizationB = Organization::query()->create([
        'name' => 'Server Logs Org B',
        'slug' => 'server-logs-org-b-'.Str::random(6),
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
        'hostname' => 'server-logs-cross.example.test',
        'ip_address' => '10.0.0.83',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $ownerA->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $this->actingAs($ownerB)
        ->getJson("/api/v1/servers/{$server->id}/logs?type=nginx_access")
        ->assertForbidden();
});

it('server logs ready event broadcasts notification without log lines', function (): void {
    $event = new \App\Modules\Servers\Events\ServerLogsReady(
        serverId: 'server-1',
        organizationId: 'org-1',
        logType: 'nginx_access',
        linesRequested: 100,
        status: 'ready',
    );

    expect($event->broadcastAs())->toBe('server.logs.ready')
        ->and($event->broadcastOn()[0]->name)->toBe('private-server.server-1.logs')
        ->and($event->broadcastWith())->not->toHaveKey('lines');
});
