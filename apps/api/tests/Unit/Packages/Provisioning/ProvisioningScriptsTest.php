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

it('install nginx runs expected command sequence', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallNginx();
    $connection = fakeSuccessfulConnection(7);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(7);
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
    $connection->addResponse('*apt-get update*', sshSuccess());
    $connection->addResponse('*certbot*', sshSuccess());

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands()[2])->toContain('python3-certbot-dns-digitalocean');
});

it('install php 8.1 installs version-specific packages', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallPHP(PhpVersion::V8_1);
    $connection = fakeSuccessfulConnection(6);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands()[3])->toContain('php8.1-fpm');
});

it('install php 8.2 installs version-specific packages', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallPHP(PhpVersion::V8_2);
    $connection = fakeSuccessfulConnection(6);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands()[3])->toContain('php8.2-fpm');
});

it('install php 8.3 installs version-specific packages', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallPHP(PhpVersion::V8_3);
    $connection = fakeSuccessfulConnection(6);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands()[3])->toContain('php8.3-fpm');
});

it('install mysql stores generated deploy password in vault', function (): void {
    [$organization, $server] = provisioningServerFixture();
    $vault = \Mockery::mock(CredentialVaultInterface::class);
    $vault->shouldReceive('storeSecret')->once();
    $script = new InstallMySQL($vault, $organization);
    $connection = fakeSuccessfulConnection(6);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(6);
});

it('install postgresql stores generated deploy password in vault', function (): void {
    [$organization, $server] = provisioningServerFixture();
    $vault = \Mockery::mock(CredentialVaultInterface::class);
    $vault->shouldReceive('storeSecret')->once();
    $script = new InstallPostgreSQL($vault, $organization);
    $connection = fakeSuccessfulConnection(5);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(5);
});

it('install redis sets password when provided and stores it in vault', function (): void {
    [$organization, $server] = provisioningServerFixture();
    $vault = \Mockery::mock(CredentialVaultInterface::class);
    $vault->shouldReceive('storeSecret')->once();
    $script = new InstallRedis($vault, $organization, 'redis-secret-123');
    $connection = fakeSuccessfulConnection(6);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(6);
});

it('install nodejs uses configured major version', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallNodejs(NodejsVersion::V22);
    $connection = fakeSuccessfulConnection(5);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands()[2])->toContain('setup_22.x');
});

it('install python runs expected command sequence', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallPython();
    $connection = fakeSuccessfulConnection(3);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(3);
});

it('install supervisor runs expected command sequence', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallSupervisor();
    $connection = fakeSuccessfulConnection(4);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(4);
});

it('install docker runs expected command sequence', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallDocker();
    $connection = fakeSuccessfulConnection(3);

    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(3);
});

it('idempotent scripts can run twice without errors', function (): void {
    $fixture = provisioningServerFixture();
    $server = $fixture[1];
    $script = new InstallSupervisor();
    $connection = fakeSuccessfulConnection(8);

    $script->handle($connection, $server);
    $script->handle($connection, $server);

    expect($connection->getExecutedCommands())->toHaveCount(8);
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
