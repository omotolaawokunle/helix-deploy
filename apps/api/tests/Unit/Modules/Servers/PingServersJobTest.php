<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Servers\Events\ServerHealthChanged;
use App\Modules\Servers\Jobs\PingServersJob;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\SSHManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

it('marks server disconnected after three consecutive ping failures', function (): void {
    Event::fake([ServerHealthChanged::class]);

    $server = createActiveServerForPingTest();
    $job = new PingServersJob();

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->times(3)->andThrow(new \RuntimeException('ping failed'));

    Redis::shouldReceive('incr')->times(3)->with("ping_failures:{$server->id}")->andReturn(1, 2, 3);
    Redis::shouldReceive('del')->never();

    $vault = app(\App\Modules\Credentials\CredentialVault::class);

    $job->handle($manager, $vault);
    $job->handle($manager, $vault);
    $job->handle($manager, $vault);

    $server->refresh();

    expect($server->status)->toBe(ServerStatus::DISCONNECTED);
    Event::assertDispatched(ServerHealthChanged::class);
});

/**
 * @return Server
 */
function createActiveServerForPingTest(): Server
{
    $organization = Organization::query()->create([
        'name' => 'Ping Org',
        'slug' => 'ping-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    return Server::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'ping.example.test',
        'ip_address' => '192.168.1.50',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => ServerStatus::ACTIVE->value,
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);
}
