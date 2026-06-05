<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Monitoring\Contracts\ServerMetricsCollectorInterface;
use App\Modules\Monitoring\Jobs\CollectServerMetricsJob;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;

it('stores collected metrics on active servers', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Metrics Org',
        'slug' => 'metrics-org',
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
        'hostname' => 'metrics.example.test',
        'ip_address' => '10.40.0.1',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => ServerStatus::ACTIVE->value,
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $fake = (new FakeSSHConnection())
        ->connect()
        ->addResponse('*awk*', new SSHResult('awk', 0, '10.00', '', 0.01))
        ->addResponse('*free -m*', new SSHResult('free', 0, '50.00 4096', '', 0.01))
        ->addResponse('*df -BG*', new SSHResult('df', 0, '30 80', '', 0.01));

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);

    $job = new CollectServerMetricsJob();
    $job->handle($manager, app(CredentialVault::class), app(ServerMetricsCollectorInterface::class));

    $server->refresh();

    expect($server->health_status)->not->toBeNull()
        ->and($server->health_status['cpuPercent'])->toEqual(10)
        ->and($server->health_status['diskUsedPercent'])->toEqual(30);
});
