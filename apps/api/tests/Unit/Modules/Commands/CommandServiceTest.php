<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Commands\Enums\CommandStatus;
use App\Modules\Commands\Exceptions\DangerousCommandException;
use App\Modules\Commands\Services\CommandCancellationService;
use App\Modules\Commands\Services\CommandService;
use App\Modules\Commands\Services\DangerousCommandGuard;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Str;

it('throws before ssh manager is called for blocked commands', function (): void {
    [$server, $actor, $org] = commandServiceFixture();

    $sshManager = \Mockery::mock(SSHManager::class);
    $sshManager->shouldNotReceive('connect');

    $service = new CommandService(
        dangerousCommandGuard: new DangerousCommandGuard(),
        sshManager: $sshManager,
        credentialVault: app(CredentialVault::class),
        cancellationService: new CommandCancellationService(),
    );

    expect(fn () => $service->queue($server, 'rm -rf /', $actor, $org))
        ->toThrow(DangerousCommandException::class);
});

it('records command execution without output in audit log', function (): void {
    [$server, $actor, $org] = commandServiceFixture();

    $fake = (new FakeSSHConnection())->connect();
    $fake->addResponse('uptime*', new SSHResult(
        command: 'uptime',
        exitCode: 0,
        stdout: "up 1 day\n",
        stderr: '',
        duration: 0.1,
    ));

    $this->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    $service = app(CommandService::class);
    $queued = $service->queue($server, 'uptime', $actor, $org);
    $command = $service->execute($queued);

    expect($command->output)->toContain('up 1 day')
        ->and($command->status)->toBe(CommandStatus::COMPLETED);

    $audit = AuditLog::query()
        ->where('operation', 'command.executed')
        ->where('resource_id', (string) $command->getKey())
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit?->after_state)->toMatchArray([
            'command_text' => 'uptime',
            'exit_code' => 0,
            'server_id' => (string) $server->getKey(),
            'status' => CommandStatus::COMPLETED->value,
        ])
        ->and(json_encode($audit?->after_state))->not->toContain('up 1 day');
});

it('marks queued command cancelled without connecting when cancellation is already requested', function (): void {
    [$server, $actor, $org] = commandServiceFixture();

    $sshManager = \Mockery::mock(SSHManager::class);
    $sshManager->shouldNotReceive('connect');

    $cancellation = new CommandCancellationService();
    $service = new CommandService(
        dangerousCommandGuard: new DangerousCommandGuard(),
        sshManager: $sshManager,
        credentialVault: app(CredentialVault::class),
        cancellationService: $cancellation,
    );

    $queued = $service->queue($server, 'uptime', $actor, $org);
    $cancellation->request((string) $queued->getKey());

    $command = $service->execute($queued);

    expect($command->status)->toBe(CommandStatus::CANCELLED);
});

/**
 * @return array{0: Server, 1: User, 2: Organization}
 */
function commandServiceFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Command Service Org',
        'slug' => 'command-service-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $actor = User::factory()->create([
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($actor->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'command-service.test',
        'ip_address' => '10.0.0.55',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $actor->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $credential = \App\Modules\Credentials\Models\Credential::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'ssh_private_key',
        'name' => 'Command Service Key',
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => 'fp',
        'created_by' => (string) $actor->getKey(),
        'last_used_at' => null,
    ]);
    $server->forceFill(['credential_id' => (string) $credential->getKey()])->save();

    return [$server, $actor, $organization];
}
