<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Events\ServerDeleted;
use App\Modules\Servers\Jobs\DeleteServerJob;
use App\Modules\Servers\Models\Server;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('delete server job removes server writes audit log and broadcasts server deleted', function (): void {
    Event::fake([ServerDeleted::class]);

    $organization = Organization::query()->create([
        'name' => 'Delete Server Org',
        'slug' => 'delete-server-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $actor = User::factory()->create();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'delete-me.test',
        'ip_address' => '10.0.0.99',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $actor->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $serverId = (string) $server->getKey();

    (new DeleteServerJob($serverId, (string) $actor->getKey()))->handle(
        app(\App\Modules\Credentials\CredentialVault::class),
    );

    expect(Server::query()->withoutGlobalScope('owned_by_organization')->whereKey($serverId)->exists())->toBeFalse();

    expect(AuditLog::query()
        ->where('operation', 'server.deleted')
        ->where('resource_id', $serverId)
        ->exists())->toBeTrue();

    Event::assertDispatched(ServerDeleted::class, function (ServerDeleted $event) use ($serverId, $organization): bool {
        return $event->serverId === $serverId
            && $event->organizationId === (string) $organization->getKey();
    });
});
