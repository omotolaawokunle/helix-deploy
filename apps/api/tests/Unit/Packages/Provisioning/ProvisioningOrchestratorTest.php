<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Modules\Servers\Models\Server;
use App\Packages\Provisioning\Contracts\ProvisioningScriptInterface;
use App\Packages\Provisioning\Exceptions\ProvisioningStepFailedException;
use App\Packages\Provisioning\ProvisioningOrchestrator;
use App\Packages\SSH\SSHConnection;
use App\Packages\SSH\SSHManager;

it('orchestrator continues when non-fatal script fails', function (): void {
    [$organization, $server] = orchestrationFixture();
    $connection = \Mockery::mock(SSHConnection::class);
    $connection->shouldReceive('disconnect')->once();

    $sshManager = \Mockery::mock(SSHManager::class);
    $sshManager->shouldReceive('connectAndVerify')->once()->andReturn($connection);

    $orchestrator = new ProvisioningOrchestrator(
        $sshManager,
        \Mockery::mock(\App\Modules\Credentials\CredentialVault::class),
    );

    $failing = new TestFailingProvisioningScript('nginx');
    $successful = new TestSuccessProvisioningScript('php');
    $lines = [];

    $orchestrator->run(
        server: $server,
        scripts: [$failing, $successful],
        lineCallback: static function (string $line) use (&$lines): void {
            $lines[] = $line;
        },
        org: $organization,
    );

    $server->refresh();
    expect($lines)->toContain('[nginx] Starting...')
        ->and($successful->executed)->toBeTrue()
        ->and($server->installed_services)->toHaveKey('php');
});

it('orchestrator aborts when create deploy user fails', function (): void {
    [$organization, $server] = orchestrationFixture();
    $connection = \Mockery::mock(SSHConnection::class);
    $connection->shouldReceive('disconnect')->once();

    $sshManager = \Mockery::mock(SSHManager::class);
    $sshManager->shouldReceive('connectAndVerify')->once()->andReturn($connection);

    $orchestrator = new ProvisioningOrchestrator(
        $sshManager,
        \Mockery::mock(\App\Modules\Credentials\CredentialVault::class),
    );

    $fatal = new TestFailingProvisioningScript('create-deploy-user');
    $after = new TestSuccessProvisioningScript('supervisor');

    expect(function () use ($orchestrator, $server, $organization, $fatal, $after): void {
        $orchestrator->run(
            server: $server,
            scripts: [$fatal, $after],
            lineCallback: static function (string $line): void {
            },
            org: $organization,
        );
    })->toThrow(ProvisioningStepFailedException::class);

    expect($after->executed)->toBeFalse();
});

/**
 * @return array{Organization, Server}
 */
function orchestrationFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Orchestrator Org',
        'slug' => 'orchestrator-org-'.\Illuminate\Support\Str::random(6),
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
        'hostname' => 'orchestrator.test',
        'ip_address' => '127.0.0.1',
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

    return [$organization, $server];
}

class TestFailingProvisioningScript implements ProvisioningScriptInterface
{
    public function __construct(private readonly string $scriptName)
    {
    }

    public function name(): string
    {
        return $this->scriptName;
    }

    public function description(): string
    {
        return 'failing test script';
    }

    public function estimatedMinutes(): int
    {
        return 1;
    }

    public function handle(\App\Packages\SSH\Contracts\SSHConnectionInterface $ssh, Server $server, array $options = []): void
    {
        throw new ProvisioningStepFailedException('forced failure');
    }

    public function isIdempotent(): bool
    {
        return true;
    }
}

class TestSuccessProvisioningScript implements ProvisioningScriptInterface
{
    public bool $executed = false;

    public function __construct(private readonly string $scriptName)
    {
    }

    public function name(): string
    {
        return $this->scriptName;
    }

    public function description(): string
    {
        return 'successful test script';
    }

    public function estimatedMinutes(): int
    {
        return 1;
    }

    public function handle(\App\Packages\SSH\Contracts\SSHConnectionInterface $ssh, Server $server, array $options = []): void
    {
        $this->executed = true;
    }

    public function isIdempotent(): bool
    {
        return true;
    }
}
