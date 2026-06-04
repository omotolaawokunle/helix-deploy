<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Daemons\DTOs\CreateDaemonDTO;
use App\Modules\Daemons\Enums\DaemonStatus;
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
    $ssh->addResponse('supervisorctl reread && supervisorctl update', new SSHResult('supervisorctl reread && supervisorctl update', 0, '', '', 0.0));
    $ssh->addResponse('supervisorctl start worker:*', new SSHResult('supervisorctl start worker:*', 0, 'worker:worker_00 RUNNING', '', 0.0));

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

    $ssh->assertCommandExecuted('supervisorctl reread && supervisorctl update');
    $ssh->assertCommandExecuted('supervisorctl start worker:*');
});
