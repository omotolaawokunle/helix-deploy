<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Servers\Models\Server;
use App\Packages\Provisioning\Enums\NodejsVersion;
use App\Packages\Provisioning\Enums\PhpVersion;
use App\Packages\Provisioning\Scripts\CreateDeployUser;
use App\Packages\Provisioning\Scripts\InstallCertbot;
use App\Packages\Provisioning\Scripts\InstallDocker;
use App\Packages\Provisioning\Scripts\InstallMySQL;
use App\Packages\Provisioning\Scripts\InstallNginx;
use App\Packages\Provisioning\Scripts\InstallNodejs;
use App\Packages\Provisioning\Scripts\InstallPHP;
use App\Packages\Provisioning\Scripts\InstallPostgreSQL;
use App\Packages\Provisioning\Scripts\InstallPython;
use App\Packages\Provisioning\Scripts\InstallRedis;
use App\Packages\Provisioning\Scripts\InstallSupervisor;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHResult;

it('create deploy user writes authorized keys with server public key', function (): void {
    [$organization, $server] = provisioningServerFixture();
    $credential = Credential::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'ssh_private_key',
        'name' => 'Test SSH Key',
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => 'fingerprint',
        'created_by' => (string) $server->created_by,
        'last_used_at' => null,
    ]);
    $server->forceFill(['credential_id' => (string) $credential->getKey()])->save();

    $publicKey = 'ssh-ed25519 AAAA-test-public-key';
    $vault = \Mockery::mock(CredentialVaultInterface::class);
    $vault->shouldReceive('getPublicKey')->once()->andReturn($publicKey);

    $script = new CreateDeployUser($vault, $organization);
    $connection = fakeSuccessfulConnection(7);

    $script->handle($connection, $server);

    $commands = $connection->getExecutedCommands();
    expect($commands[3])->toContain($publicKey);
});

it('install nginx runs expected command sequence on fresh server', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallNginx();
    $connection = nginxProvisioningConnection(nginxInstalled: false);

    $script->handle($connection, $server);

    $commands = $connection->getExecutedCommands();

    expect($commands)->toHaveCount(8)
        ->and(collect($commands)->first(fn (string $command): bool => str_contains($command, 'apt-get install')))->toContain('--no-install-recommends')
        ->and(collect($commands)->first(fn (string $command): bool => str_contains($command, '/etc/nginx/nginx.conf')))->not->toBeNull();
});

it('install nginx preserves existing configuration when nginx is already installed', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallNginx();
    $connection = nginxProvisioningConnection(nginxInstalled: true);

    $script->handle($connection, $server);

    $commands = $connection->getExecutedCommands();

    expect($commands)->toHaveCount(5);

    foreach ($commands as $command) {
        expect($command)->not->toContain('/etc/nginx/nginx.conf');
        expect($command)->not->toContain('apt-get install');
    }
});

it('install nginx disables apache when present', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallNginx();
    $connection = nginxProvisioningConnection(nginxInstalled: true);

    $script->handle($connection, $server);

    expect(collect($connection->getExecutedCommands())->contains(
        fn (string $command): bool => str_contains($command, 'systemctl disable apache2'),
    ))->toBeTrue();
});

it('install certbot skips when already installed', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallCertbot();
    $connection = (new FakeSSHConnection())->connect();
    $connection->addResponse('*command -v certbot*', new SSHResult('cmd', 0, 'yes', '', 0.01));

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(1);
});

it('install certbot installs packages when missing', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallCertbot();
    $connection = (new FakeSSHConnection())->connect();
    $connection->addResponse('*command -v certbot*', new SSHResult('cmd', 0, 'no', '', 0.01));
    $connection->addResponse('*apt-get update*', provisioningScriptSshSuccess());
    $connection->addResponse('*certbot*', provisioningScriptSshSuccess());

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands()[2])->toContain('python3-certbot-dns-digitalocean')
        ->and($connection->getExecutedCommands()[2])->toContain('--no-install-recommends');
});

it('install php 8.1 installs version-specific packages', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallPHP(PhpVersion::V8_1);
    $connection = phpProvisioningConnection(fpmInstalled: false, nginxInstalled: false);

    $script->handle($connection, $server);

    $installCommand = collect($connection->getExecutedCommands())->first(
        fn (string $command): bool => str_contains($command, 'apt-get install') && str_contains($command, 'php8.1-fpm'),
    );

    expect($installCommand)->not->toBeNull()
        ->and($installCommand)->toContain('--no-install-recommends');
});

it('install php 8.2 installs version-specific packages', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallPHP(PhpVersion::V8_2);
    $connection = phpProvisioningConnection(fpmInstalled: false, nginxInstalled: false);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands()[5])->toContain('php8.2-fpm');
});

it('install php 8.3 installs version-specific packages', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallPHP(PhpVersion::V8_3);
    $connection = phpProvisioningConnection(fpmInstalled: false, nginxInstalled: false);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands()[5])->toContain('php8.3-fpm');
});

it('install php skips package installation when fpm is already installed', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallPHP(PhpVersion::V8_3);
    $connection = phpProvisioningConnection(fpmInstalled: true, nginxInstalled: true);

    $script->handle($connection, $server);

    $commands = $connection->getExecutedCommands();

    expect($commands)->toHaveCount(4)
        ->and(collect($commands)->first(fn (string $command): bool => str_contains($command, 'dpkg -s')))->not->toBeNull()
        ->and(collect($commands)->contains(fn (string $command): bool => str_contains($command, 'apt-get install')))->toBeFalse()
        ->and(collect($commands)->contains(fn (string $command): bool => str_contains($command, 'systemctl disable apache2')))->toBeTrue();
});

it('install mysql stores generated deploy password in vault', function (): void {
    [$organization, $server] = provisioningServerFixture();
    $vault = \Mockery::mock(CredentialVaultInterface::class);
    $vault->shouldReceive('storeServerSecret')->once();
    $script = new InstallMySQL($vault, $organization);
    $connection = serviceProvisioningConnection(serviceInstalled: false, installSteps: 6);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(7);
});

it('install mysql skips package installation when mysql is already installed', function (): void {
    [$organization, $server] = provisioningServerFixture();
    $vault = \Mockery::mock(CredentialVaultInterface::class);
    $vault->shouldReceive('storeServerSecret')->never();
    $script = new InstallMySQL($vault, $organization);
    $connection = serviceProvisioningConnection(serviceInstalled: true, installSteps: 0, serviceBinary: 'mysql');

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(3);
});

it('install postgresql stores generated deploy password in vault', function (): void {
    [$organization, $server] = provisioningServerFixture();
    $vault = \Mockery::mock(CredentialVaultInterface::class);
    $vault->shouldReceive('storeServerSecret')->once();
    $script = new InstallPostgreSQL($vault, $organization);
    $connection = serviceProvisioningConnection(serviceInstalled: false, installSteps: 7);

    $script->handle($connection, $server, ['postgresqlVersion' => '18']);

    expect(collect($connection->getExecutedCommands())->contains(
        fn (string $command): bool => str_contains($command, 'postgresql-18'),
    ))->toBeTrue();
});

it('install redis sets password when provided and stores it in vault', function (): void {
    [$organization, $server] = provisioningServerFixture();
    $vault = \Mockery::mock(CredentialVaultInterface::class);
    $vault->shouldReceive('storeServerSecret')->once();
    $script = new InstallRedis($vault, $organization, 'redis-secret-123');
    $connection = serviceProvisioningConnection(serviceInstalled: false, installSteps: 6, serviceBinary: 'redis-cli');

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(7);
});

it('install nodejs uses configured major version', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallNodejs(NodejsVersion::V22);
    $connection = serviceProvisioningConnection(serviceInstalled: false, installSteps: 5, serviceBinary: 'node');

    $script->handle($connection, $server);

    expect(collect($connection->getExecutedCommands())->contains(
        fn (string $command): bool => str_contains($command, 'setup_22.x'),
    ))->toBeTrue();
});

it('install python runs expected command sequence', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallPython();
    $connection = serviceProvisioningConnection(serviceInstalled: false, installSteps: 5, serviceBinary: 'python3.12');

    $script->handle($connection, $server, ['pythonVersion' => '3.12']);

    expect(collect($connection->getExecutedCommands())->contains(
        fn (string $command): bool => str_contains($command, 'python3.12'),
    ))->toBeTrue();
});

it('install supervisor runs expected command sequence', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallSupervisor();
    $connection = serviceProvisioningConnection(serviceInstalled: false, installSteps: 4, serviceBinary: 'supervisorctl');

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(5);
});

it('install docker runs expected command sequence', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallDocker();
    $connection = serviceProvisioningConnection(serviceInstalled: false, installSteps: 3, serviceBinary: 'docker');

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(4);
});

it('idempotent scripts can run twice without errors', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallSupervisor();
    $connection = (new FakeSSHConnection())->connect();
    $connection->addSequence(
        '*command -v*supervisorctl*',
        new SSHResult('cmd', 0, 'yes', '', 0.01),
        new SSHResult('cmd', 0, 'yes', '', 0.01),
    );
    $connection->addSequence('*', ...array_fill(0, 6, provisioningScriptSshSuccess()));

    $script->handle($connection, $server);
    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(6);
});

/**
 * @return array{Organization, Server}
 */
function provisioningServerFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Provisioning Scripts Org',
        'slug' => 'provisioning-scripts-org-'.\Illuminate\Support\Str::random(6),
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
        'hostname' => 'provisioning-script.test',
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

function fakeSuccessfulConnection(int $stepCount): FakeSSHConnection
{
    $connection = (new FakeSSHConnection())->connect();
    $responses = [];

    foreach (range(1, $stepCount) as $index) {
        $responses[] = new SSHResult(
            command: "step-{$index}",
            exitCode: 0,
            stdout: 'ok',
            stderr: '',
            duration: 0.01,
        );
    }

    $connection->addSequence('*', ...$responses);

    return $connection;
}

function provisioningScriptSshSuccess(): SSHResult
{
    return new SSHResult('cmd', 0, 'ok', '', 0.01);
}

function nginxProvisioningConnection(bool $nginxInstalled): FakeSSHConnection
{
    $connection = (new FakeSSHConnection())->connect();
    $connection->addResponse(
        '*command -v*nginx*',
        new SSHResult('cmd', 0, $nginxInstalled ? 'yes' : 'no', '', 0.01),
    );
    $connection->addSequence('*', ...array_fill(0, 12, provisioningScriptSshSuccess()));

    return $connection;
}

function phpProvisioningConnection(bool $fpmInstalled, bool $nginxInstalled): FakeSSHConnection
{
    $connection = (new FakeSSHConnection())->connect();
    $connection->addResponse(
        '*dpkg -s*',
        new SSHResult('cmd', 0, $fpmInstalled ? 'yes' : 'no', '', 0.01),
    );

    if ($fpmInstalled) {
        $connection->addResponse(
            '*command -v*nginx*',
            new SSHResult('cmd', 0, $nginxInstalled ? 'yes' : 'no', '', 0.01),
        );
    }

    $connection->addSequence('*', ...array_fill(0, 12, provisioningScriptSshSuccess()));

    return $connection;
}

function serviceProvisioningConnection(
    bool $serviceInstalled,
    int $installSteps,
    string $serviceBinary = 'mysql',
): FakeSSHConnection {
    $connection = (new FakeSSHConnection())->connect();
    $connection->addResponse(
        "*command -v*{$serviceBinary}*",
        new SSHResult('cmd', 0, $serviceInstalled ? 'yes' : 'no', '', 0.01),
    );

    if ($serviceBinary === 'python3' && $serviceInstalled) {
        $connection->addResponse(
            '*command -v*gunicorn*',
            new SSHResult('cmd', 0, 'yes', '', 0.01),
        );
    }

    if ($serviceBinary === 'node' && $serviceInstalled) {
        $connection->addResponse(
            '*command -v*pm2*',
            new SSHResult('cmd', 0, 'yes', '', 0.01),
        );
    }

    $connection->addSequence('*', ...array_fill(0, max($installSteps + 4, 6), provisioningScriptSshSuccess()));

    return $connection;
}
