<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Monitoring\Models\InfrastructureEvent;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Servers\Events\ServerConnected;
use App\Modules\Servers\Events\ServerConnectionFailed;
use App\Modules\Servers\Events\ServerFingerprintMismatch;
use App\Modules\Servers\Jobs\VerifyServerConnectionJob;
use App\Modules\Sites\Jobs\AdoptServerSslCertificatesJob;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\Exceptions\SSHFingerprintMismatchException;
use App\Packages\SSH\SSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('verify job success updates status versions and broadcasts server connected', function (): void {
    Event::fake([ServerConnected::class]);
    Queue::fake();

    $server = createServerForVerifyJob();
    $job = new VerifyServerConnectionJob((string) $server->getKey());

    $connection = \Mockery::mock(SSHConnection::class);
    $connection->shouldReceive('run')
        ->once()
        ->andReturn(new SSHResult(
            command: 'probe',
            exitCode: 0,
            stdout: "_ok_\nLinux host 6.8.0\nUbuntu 24.04 LTS\nPHP 8.3.1 (cli)\nv20.12.2\n",
            stderr: '',
            duration: 0.1,
        ));
    $connection->shouldReceive('disconnect')->once();

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connectAndVerify')->once()->andReturn($connection);

    $syncInventory = \Mockery::mock(\App\Modules\Servers\Actions\SyncServerInventoryAction::class);
    $syncInventory->shouldReceive('execute')
        ->once()
        ->andReturn([
            'installedServices' => [
                'nginx' => ['installed' => true, 'source' => 'introspection'],
            ],
            'sitesCreated' => 1,
            'sitesUpdated' => 0,
            'discoveredSiteCount' => 1,
        ]);

    $job->handle(
        $manager,
        app(\App\Modules\Credentials\CredentialVault::class),
        app(\App\Modules\Servers\Actions\ReportFingerprintMismatchAction::class),
        $syncInventory,
    );

    $server->refresh();

    expect($server->status)->toBe(ServerStatus::ACTIVE)
        ->and($server->os)->toContain('Linux')
        ->and($server->php_version)->toContain('PHP')
        ->and($server->node_version)->toBe('v20.12.2');

    Event::assertDispatched(ServerConnected::class);
    expect(InfrastructureEvent::query()->where('event_type', 'server.connected')->exists())->toBeTrue();
    Queue::assertPushed(AdoptServerSslCertificatesJob::class);
});

it('verify job fingerprint mismatch marks disconnected broadcasts mismatch and does not throw', function (): void {
    Event::fake([ServerFingerprintMismatch::class]);

    $server = createServerForVerifyJob();
    $job = new VerifyServerConnectionJob((string) $server->getKey());

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connectAndVerify')
        ->once()
        ->andThrow(new SSHFingerprintMismatchException(
            server: $server,
            expectedFingerprint: 'expected',
            receivedFingerprint: 'received',
        ));

    $job->handle(
        $manager,
        app(\App\Modules\Credentials\CredentialVault::class),
        app(\App\Modules\Servers\Actions\ReportFingerprintMismatchAction::class),
        \Mockery::mock(\App\Modules\Servers\Actions\SyncServerInventoryAction::class),
    );

    $server->refresh();

    expect($server->status)->toBe(ServerStatus::DISCONNECTED);
    Event::assertDispatched(ServerFingerprintMismatch::class);
    expect(InfrastructureEvent::query()
        ->where('event_type', 'server.fingerprint_mismatch')
        ->where('server_id', $server->id)
        ->exists())->toBeTrue();
});

it('verify job uses expected retry backoff for network failures', function (): void {
    Event::fake([ServerConnectionFailed::class]);

    $server = createServerForVerifyJob();
    $job = new VerifyServerConnectionJob((string) $server->getKey());

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connectAndVerify')
        ->once()
        ->andThrow(new \RuntimeException('network unreachable'));

    expect(fn () =>     $job->handle(
        $manager,
        app(\App\Modules\Credentials\CredentialVault::class),
        app(\App\Modules\Servers\Actions\ReportFingerprintMismatchAction::class),
        app(\App\Modules\Servers\Actions\SyncServerInventoryAction::class),
    ))->toThrow(\RuntimeException::class);
    expect($job->tries)->toBe(5);
    expect($job->backoff())->toBe([30, 60, 120, 300, 600]);
});

/**
 * @return Server
 */
function createServerForVerifyJob(): Server
{
    $organization = Organization::query()->create([
        'name' => 'Verify Job Org',
        'slug' => 'verify-job-org',
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
        'hostname' => 'verify.example.test',
        'ip_address' => '127.0.0.1',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => ServerStatus::CONNECTING->value,
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);
}
