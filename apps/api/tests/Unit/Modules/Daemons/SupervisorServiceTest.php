<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Daemons\DTOs\CreateDaemonDTO;
use App\Modules\Daemons\Enums\DaemonStatus;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Daemons\Services\SupervisorService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Str;

it('runs the expected ssh command sequence when creating a daemon', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Daemon Service Org',
        'slug' => 'daemon-service-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $actor = User::factory()->create();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'daemon-service.test',
        'ip_address' => '10.0.0.23',
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
    $ssh->addResponse('sudo cp */tmp/helix-supervisor-worker.conf*', new SSHResult('sudo cp', 0, '', '', 0.0));
    $ssh->addResponse('sudo supervisorctl reread && sudo supervisorctl update', new SSHResult('sudo supervisorctl reread && sudo supervisorctl update', 0, '', '', 0.0));
    $ssh->addResponse("sudo supervisorctl start 'worker:*'", new SSHResult('sudo supervisorctl start', 0, '', '', 0.0));
    $ssh->addResponse(
        "sudo supervisorctl status 'worker:*'",
        new SSHResult('sudo supervisorctl status', 0, 'worker:worker_00 RUNNING pid 1, uptime 0:00:01', '', 0.0),
    );

    $daemon = app(SupervisorService::class)->create(
        $server,
        $ssh->connect(),
        new CreateDaemonDTO(
            name: 'worker',
            command: 'php artisan queue:work',
            directory: '/var/www/app/current',
            user: 'www-data',
            processes: 1,
        ),
        $actor,
    );

    expect($daemon->status)->toBe(DaemonStatus::RUNNING);
    expect($daemon->config_path)->toBe('/etc/supervisor/conf.d/worker.conf');
    expect($ssh->getUploads())->toHaveKey('/tmp/helix-supervisor-worker.conf');
    expect($ssh->getUploads()['/tmp/helix-supervisor-worker.conf'])->toContain('process_name=%(program_name)s_%(process_num)02d');

    $ssh->assertCommandExecuted('sudo cp */tmp/helix-supervisor-worker.conf*');
    $ssh->assertCommandExecuted('sudo supervisorctl reread && sudo supervisorctl update');
    $ssh->assertCommandExecuted("sudo supervisorctl start 'worker:*'");
    $ssh->assertCommandExecuted("sudo supervisorctl status 'worker:*'");
});

it('marks daemon running when start output is empty but status reports RUNNING', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Daemon Status Org',
        'slug' => 'daemon-status-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $actor = User::factory()->create();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'daemon-status.test',
        'ip_address' => '10.0.0.25',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $actor->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $daemon = SupervisorProcess::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'name' => 'horizon',
        'command' => 'php artisan horizon',
        'directory' => '/var/www/app/current',
        'user' => 'www-data',
        'processes' => 1,
        'status' => DaemonStatus::STOPPED,
        'config_path' => '/etc/supervisor/conf.d/horizon.conf',
        'created_by' => (string) $actor->getKey(),
    ]);

    $ssh = new FakeSSHConnection();
    $ssh->addResponse("sudo supervisorctl start 'horizon:*'", new SSHResult('start', 0, '', '', 0.0));
    $ssh->addResponse(
        "sudo supervisorctl status 'horizon:*'",
        new SSHResult('status', 0, 'horizon:horizon_00   RUNNING   pid 9, uptime 0:01:00', '', 0.0),
    );

    $updated = app(SupervisorService::class)->start($daemon, $ssh->connect());

    expect($updated->status)->toBe(DaemonStatus::RUNNING);
});
