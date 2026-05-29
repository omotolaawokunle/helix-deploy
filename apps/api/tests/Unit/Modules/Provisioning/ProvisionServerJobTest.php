<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Provisioning\Events\ProvisioningCompleted;
use App\Modules\Provisioning\Jobs\ProvisionServerJob;
use App\Modules\Provisioning\Models\ProvisioningLog;
use App\Modules\Servers\Models\Server;
use App\Packages\Provisioning\ProvisioningOrchestrator;
use App\Packages\SSH\SSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('provision server job updates installed services after completion', function (): void {
    Event::fake([ProvisioningCompleted::class]);
    [$organization, $server, $actor] = provisioningJobFixture();

    $connection = \Mockery::mock(SSHConnection::class);
    $connection->shouldReceive('run')
        ->times(4)
        ->andReturn(
            new SSHResult('1', 0, 'ok', '', 0.01),
            new SSHResult('2', 0, 'ok', '', 0.01),
            new SSHResult('3', 0, 'ok', '', 0.01),
            new SSHResult('4', 0, 'ok', '', 0.01),
        );
    $connection->shouldReceive('disconnect')->once();
    $sshManager = \Mockery::mock(SSHManager::class);
    $sshManager->shouldReceive('connectAndVerify')->once()->andReturn($connection);

    $orchestrator = new ProvisioningOrchestrator(
        $sshManager,
        \Mockery::mock(\App\Modules\Credentials\CredentialVault::class),
    );

    $job = new ProvisionServerJob(
        serverId: (string) $server->getKey(),
        actorId: (string) $actor->getKey(),
        runId: (string) Str::uuid(),
        scripts: ['supervisor'],
        options: [],
    );

    $job->handle($orchestrator, \Mockery::mock(CredentialVaultInterface::class));

    $server->refresh();

    expect($server->installed_services)->toHaveKey('supervisor');
    expect(ProvisioningLog::query()->where('server_id', $server->getKey())->exists())->toBeTrue();
    expect(AuditLog::query()->where('operation', 'server.provisioned')->exists())->toBeTrue();
    Event::assertDispatched(ProvisioningCompleted::class);
});

/**
 * @return array{Organization, Server, User}
 */
function provisioningJobFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Provisioning Job Org',
        'slug' => 'provisioning-job-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($user->getKey(), ['role' => 'owner']);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'job-provisioning.test',
        'ip_address' => '10.0.0.30',
        'ssh_port' => 22,
        'ssh_user' => 'root',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'credential_id' => null,
        'created_by' => (string) $user->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    return [$organization, $server, $user];
}
