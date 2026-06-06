<?php

declare(strict_types=1);

use App\Modules\Daemons\Enums\DaemonStatus;
use App\Modules\Daemons\Events\DaemonChanged;
use App\Modules\Daemons\Events\DaemonLogsReady;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Models\User;
use Illuminate\Support\Str;

it('daemon changed event broadcasts on server and organization channels', function (): void {
    $event = new DaemonChanged(
        serverId: 'server-1',
        organizationId: 'org-1',
        daemonId: 'daemon-1',
        action: 'updated',
        daemonSnapshot: ['id' => 'daemon-1', 'status' => 'running'],
    );

    $channels = $event->broadcastOn();

    expect($event->broadcastAs())->toBe('daemon.changed')
        ->and($channels)->toHaveCount(2)
        ->and($event->broadcastWith()['action'])->toBe('updated');
});

it('daemon logs ready event broadcasts on server daemons channel', function (): void {
    $event = new DaemonLogsReady(
        serverId: 'server-1',
        organizationId: 'org-1',
        daemonId: 'daemon-1',
        status: 'ready',
        lines: ['line one'],
    );

    expect($event->broadcastAs())->toBe('daemon.logs.ready')
        ->and($event->broadcastWith()['lines'])->toBe(['line one']);
});

it('daemon changed created factory includes daemon snapshot', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Daemon Event Org',
        'slug' => 'daemon-event-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $actor = User::factory()->create();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'daemon-event.test',
        'ip_address' => '10.0.0.24',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $actor->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $daemon = SupervisorProcess::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'name' => 'queue-worker',
        'command' => 'php artisan queue:work',
        'directory' => '/var/www/app',
        'user' => 'www-data',
        'processes' => 1,
        'status' => DaemonStatus::RUNNING->value,
        'config_path' => '/etc/supervisor/conf.d/queue-worker.conf',
        'created_by' => (string) $actor->getKey(),
    ]);

    $event = DaemonChanged::created($daemon);

    expect($event->action)->toBe('created')
        ->and($event->broadcastWith()['daemon']['name'])->toBe('queue-worker');
});
