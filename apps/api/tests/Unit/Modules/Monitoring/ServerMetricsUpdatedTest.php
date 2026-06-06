<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Monitoring\Events\ServerMetricsUpdated;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;

it('broadcasts metric fields on the organization channel', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Broadcast Org',
        'slug' => 'broadcast-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'broadcast.example.test',
        'ip_address' => '10.40.0.2',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => ServerStatus::ACTIVE->value,
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
        'health_status' => [
            'cpuPercent' => 12.5,
            'memoryUsedPercent' => 55.0,
            'diskUsedPercent' => 40.0,
            'lastCheckedAt' => '2026-06-05T12:00:00+00:00',
        ],
    ]);

    $event = new ServerMetricsUpdated($server);
    $payload = $event->broadcastWith();

    expect($event->broadcastAs())->toBe('server.metrics_updated')
        ->and($payload)->toMatchArray([
            'serverId' => (string) $server->getKey(),
            'cpuPercent' => 12.5,
            'memoryUsedPercent' => 55.0,
            'diskUsedPercent' => 40.0,
            'lastCheckedAt' => '2026-06-05T12:00:00+00:00',
        ]);
});
