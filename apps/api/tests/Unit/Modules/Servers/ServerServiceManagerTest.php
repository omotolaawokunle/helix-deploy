<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Enums\ServiceRuntimeStatus;
use App\Modules\Servers\Services\InstalledServiceRegistry;
use App\Modules\Servers\Services\ServerServiceManager;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Str;

it('resolves php fpm unit from server php version', function (): void {
    $registry = app(InstalledServiceRegistry::class);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->make([
        'php_version' => '8.2',
    ]);

    expect($registry->unitFor($server, 'php'))->toBe('php8.2-fpm');
    expect($registry->unitFor($server, 'nginx'))->toBe('nginx');
    expect($registry->isControllable('nodejs'))->toBeFalse();
    expect($registry->isControllable('nginx'))->toBeTrue();
});

it('defaults php fpm unit when php version is missing', function (): void {
    $registry = app(InstalledServiceRegistry::class);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->make([
        'php_version' => null,
    ]);

    expect($registry->unitFor($server, 'php'))->toBe('php8.3-fpm');
});

it('returns installed controllable keys only', function (): void {
    $registry = app(InstalledServiceRegistry::class);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->make([
        'installed_services' => [
            'nginx' => ['installed' => true],
            'nodejs' => ['installed' => true],
            'redis' => ['installed' => false],
        ],
    ]);

    expect($registry->installedControllableKeys($server))->toBe(['nginx']);
});

it('runs systemctl commands when starting a service', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Service Manager Org',
        'slug' => 'service-manager-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $actor = User::factory()->create();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'service-manager.test',
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

    $ssh = new FakeSSHConnection();
    $ssh->addResponse('systemctl is-active *', new SSHResult('systemctl is-active nginx', 0, 'inactive', '', 0.0));
    $ssh->addResponse('sudo systemctl start *', new SSHResult('sudo systemctl start nginx', 0, '', '', 0.0));
    $ssh->addResponse('systemctl is-active *', new SSHResult('systemctl is-active nginx', 0, 'active', '', 0.0));

    $status = app(ServerServiceManager::class)->start($ssh->connect(), $server, 'nginx');

    expect($status)->toBe(ServiceRuntimeStatus::RUNNING);
    $ssh->assertCommandExecuted('sudo systemctl start *nginx*');
});

it('syncs multiple service statuses in one ssh session', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Service Sync Org',
        'slug' => 'service-sync-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $actor = User::factory()->create();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'service-sync.test',
        'ip_address' => '10.0.0.56',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $actor->getKey(),
        'tags' => [],
        'installed_services' => [
            'nginx' => ['installed' => true],
            'redis' => ['installed' => true],
        ],
    ]);

    $ssh = new FakeSSHConnection();
    $ssh->addResponse('*', new SSHResult('status-script', 0, <<<'OUT'
STATUS:nginx|active
STATUS:redis|inactive
OUT, '', 0.0));

    $statuses = app(ServerServiceManager::class)->syncStatuses(
        $ssh->connect(),
        $server,
        ['nginx', 'redis'],
    );

    expect($statuses)->toBe([
        'nginx' => ServiceRuntimeStatus::RUNNING,
        'redis' => ServiceRuntimeStatus::STOPPED,
    ]);
});

it('records audit logs when restarting a service', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Service Audit Org',
        'slug' => 'service-audit-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $actor = User::factory()->create();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'service-audit.test',
        'ip_address' => '10.0.0.58',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $actor->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $ssh = new FakeSSHConnection();
    $ssh->addResponse('systemctl is-active *', new SSHResult('systemctl is-active nginx', 0, 'active', '', 0.0));
    $ssh->addResponse('sudo systemctl restart *', new SSHResult('sudo systemctl restart nginx', 0, '', '', 0.0));
    $ssh->addResponse('systemctl is-active *', new SSHResult('systemctl is-active nginx', 0, 'active', '', 0.0));

    app(ServerServiceManager::class)->restart($ssh->connect(), $server, 'nginx');

    expect(AuditLog::query()->where('operation', 'server.service.restarted')->exists())->toBeTrue();
});
