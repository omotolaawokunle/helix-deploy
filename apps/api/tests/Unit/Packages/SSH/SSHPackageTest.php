<?php

declare(strict_types=1);

namespace Tests\Unit\Packages\SSH;

use App\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Exceptions\SSHCommandFailedException;
use App\Packages\SSH\Exceptions\SSHFingerprintMismatchException;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHConnection;
use App\Packages\SSH\SSHResult;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use phpseclib3\Crypt\EC;
use phpseclib3\Net\SSH2;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class SSHPackageTest extends TestCase
{
    private Capsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        Container::setInstance($container);

        $this->capsule = new Capsule($container);
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $this->createSchema();
    }

    public function test_connect_stores_fingerprint_when_missing(): void
    {
        $organization = $this->createOrganization();
        $server = $this->createServer($organization, null);
        $privateKey = EC::createKey('Ed25519')->toString('OpenSSH', ['password' => '']);

        $fakeSsh = new FakePhpseclibSSH2(hostKey: 'host-key-a', loginResult: true);
        $connection = new TestableSSHConnection($server, $privateKey, $fakeSsh);

        $connection->connect();
        $server->refresh();

        self::assertSame(hash('sha256', 'host-key-a'), $server->fingerprint);
        self::assertTrue(
            \App\Modules\Audit\Models\AuditLog::query()->where('operation', 'server.fingerprint_stored')->exists()
        );
    }

    public function test_connect_passes_when_fingerprint_matches(): void
    {
        $organization = $this->createOrganization();
        $expectedFingerprint = hash('sha256', 'host-key-b');
        $server = $this->createServer($organization, $expectedFingerprint);
        $privateKey = EC::createKey('Ed25519')->toString('OpenSSH', ['password' => '']);

        $fakeSsh = new FakePhpseclibSSH2(hostKey: 'host-key-b', loginResult: true);
        $connection = new TestableSSHConnection($server, $privateKey, $fakeSsh);

        $connected = $connection->connect();

        self::assertSame($connection, $connected);
        self::assertTrue($connection->isConnected());
    }

    public function test_connect_throws_on_fingerprint_mismatch_and_does_not_update(): void
    {
        $organization = $this->createOrganization();
        $server = $this->createServer($organization, 'expected-fingerprint');
        $privateKey = EC::createKey('Ed25519')->toString('OpenSSH', ['password' => '']);

        $fakeSsh = new FakePhpseclibSSH2(hostKey: 'different-host-key', loginResult: true);
        $connection = new TestableSSHConnection($server, $privateKey, $fakeSsh);

        try {
            $connection->connect();
            self::fail('Expected SSHFingerprintMismatchException to be thrown.');
        } catch (SSHFingerprintMismatchException $exception) {
            self::assertSame('expected-fingerprint', $exception->expectedFingerprint);
            self::assertSame(hash('sha256', 'different-host-key'), $exception->receivedFingerprint);
        }

        $server->refresh();
        self::assertSame('expected-fingerprint', $server->fingerprint);
    }

    public function test_fingerprint_mismatch_does_not_update_stored_fingerprint(): void
    {
        $organization = $this->createOrganization();
        $server = $this->createServer($organization, 'known-fingerprint');
        $privateKey = EC::createKey('Ed25519')->toString('OpenSSH', ['password' => '']);

        $fakeSsh = new FakePhpseclibSSH2(hostKey: 'new-host-key', loginResult: true);
        $connection = new TestableSSHConnection($server, $privateKey, $fakeSsh);

        try {
            $connection->connect();
            self::fail('Expected SSHFingerprintMismatchException to be thrown.');
        } catch (SSHFingerprintMismatchException) {
        }

        $server->refresh();
        self::assertSame('known-fingerprint', $server->fingerprint);
    }

    public function test_fake_connection_records_commands_in_order(): void
    {
        $fake = (new FakeSSHConnection())
            ->addResponse('*first*', new SSHResult('echo first', 0, 'first', '', 0.01))
            ->addResponse('*second*', new SSHResult('echo second', 0, 'second', '', 0.01));

        $fake->connect();
        $fake->run('run first command');
        $fake->run('run second command');

        self::assertSame(['run first command', 'run second command'], $fake->getExecutedCommands());
        $fake->assertCommandExecuted('*first*');
        $fake->assertCommandNotExecuted('*third*');
        $fake->assertCommandCount(2);
    }

    public function test_fake_connection_throws_for_unmatched_command(): void
    {
        $fake = new FakeSSHConnection();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FakeSSHConnection: unmatched command: ls -la');

        $fake->run('ls -la');
    }

    public function test_ssh_result_throw_raises_exception_when_failed(): void
    {
        $result = new SSHResult(
            command: 'bad command',
            exitCode: 1,
            stdout: '',
            stderr: 'permission denied',
            duration: 0.3,
        );

        $this->expectException(SSHCommandFailedException::class);
        $result->throw();
    }

    public function test_ssh_result_throw_returns_self_when_successful(): void
    {
        $result = new SSHResult(
            command: 'echo ok',
            exitCode: 0,
            stdout: 'ok',
            stderr: '',
            duration: 0.01,
        );

        self::assertSame($result, $result->throw());
    }

    private function createSchema(): void
    {
        $schema = $this->capsule->schema();

        $schema->create('organizations', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->text('master_key_encrypted');
            $table->text('settings')->nullable();
            $table->string('owner_id');
            $table->timestamps();
        });

        $schema->create('servers', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id');
            $table->string('hostname');
            $table->string('ip_address');
            $table->unsignedSmallInteger('ssh_port');
            $table->string('ssh_user');
            $table->string('fingerprint')->nullable();
            $table->string('credential_id')->nullable();
            $table->string('created_by');
            $table->timestamps();
        });

        $schema->create('audit_logs', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id');
            $table->string('actor_id')->nullable();
            $table->string('operation');
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->text('before_state')->nullable();
            $table->text('after_state')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    private function createOrganization(): Organization
    {
        $organizationId = Uuid::uuid4()->toString();

        $this->capsule->table('organizations')->insert([
            'id' => $organizationId,
            'name' => 'SSH Org',
            'slug' => 'ssh-org',
            'master_key_encrypted' => '{}',
            'settings' => '{}',
            'owner_id' => Uuid::uuid4()->toString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Organization::query()->findOrFail($organizationId);
    }

    private function createServer(Organization $organization, ?string $fingerprint): Server
    {
        $serverId = Uuid::uuid4()->toString();

        $this->capsule->table('servers')->insert([
            'id' => $serverId,
            'organization_id' => (string) $organization->getKey(),
            'hostname' => 'ssh-host',
            'ip_address' => '127.0.0.1',
            'ssh_port' => 22,
            'ssh_user' => 'deploy',
            'fingerprint' => $fingerprint,
            'credential_id' => null,
            'created_by' => Uuid::uuid4()->toString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Server::query()->findOrFail($serverId);
    }
}

class FakePhpseclibSSH2 extends SSH2
{
    private int $exitStatus = 0;

    private string $stderr = '';

    private bool $timeoutDetected = false;

    public function __construct(
        private readonly string $hostKey,
        private readonly bool $loginResult,
    ) {
    }

    public function login($username, ...$args): bool
    {
        return $this->loginResult;
    }

    public function getServerPublicHostKey()
    {
        return $this->hostKey;
    }

    public function exec($command, $callback = null)
    {
        if ($callback !== null) {
            $callback("output line 1\noutput line 2");
        }

        return "output line 1\noutput line 2";
    }

    public function getExitStatus()
    {
        return $this->exitStatus;
    }

    public function getStdError(): string
    {
        return $this->stderr;
    }

    public function setTimeout($timeout): void
    {
    }

    public function isTimeout(): bool
    {
        return $this->timeoutDetected;
    }

    public function disconnect(): void
    {
    }
}

class TestableSSHConnection extends SSHConnection
{
    public function __construct(
        Server $server,
        string $privateKeyContent,
        private readonly SSH2 $testClient,
    ) {
        parent::__construct($server, $privateKeyContent);
    }

    protected function createSSHClient(string $host, int $port): SSH2
    {
        return $this->testClient;
    }
}
