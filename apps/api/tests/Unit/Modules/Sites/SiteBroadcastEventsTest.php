<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Events\SiteCreated;
use App\Modules\Sites\Events\SiteProvisioningFailed;
use App\Modules\Sites\Events\SiteProvisioningStarted;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('site provisioning started broadcasts on server and organization channels', function (): void {
    Event::fake([SiteProvisioningStarted::class]);

    $site = siteForBroadcastEvent();

    event(new SiteProvisioningStarted($site));

    Event::assertDispatched(SiteProvisioningStarted::class, function (SiteProvisioningStarted $event) use ($site): bool {
        expect($event->broadcastAs())->toBe('site.provisioning.started')
            ->and($event->broadcastOn())->toEqual([
                new PrivateChannel('server.'.$site->server_id.'.sites'),
                new PrivateChannel('organizations.'.$site->organization_id),
            ])
            ->and($event->broadcastWith()['siteId'])->toBe((string) $site->getKey());

        return true;
    });
});

it('site created broadcasts active status payload', function (): void {
    $site = siteForBroadcastEvent();
    $site->forceFill(['status' => SiteStatus::ACTIVE->value])->save();

    $event = new SiteCreated($site->refresh());

    expect($event->broadcastAs())->toBe('site.created')
        ->and($event->broadcastWith()['status'])->toBe(SiteStatus::ACTIVE->value);
});

it('site provisioning failed includes removal flag', function (): void {
    $site = siteForBroadcastEvent();

    $event = new SiteProvisioningFailed(
        siteId: (string) $site->getKey(),
        serverId: (string) $site->server_id,
        organizationId: (string) $site->organization_id,
        domain: $site->domain,
        message: 'syntax error',
        siteRemoved: true,
    );

    expect($event->broadcastAs())->toBe('site.provisioning.failed')
        ->and($event->broadcastWith()['siteRemoved'])->toBeTrue();
});

function siteForBroadcastEvent(): Site
{
    $organization = Organization::query()->create([
        'name' => 'Broadcast Org',
        'slug' => 'broadcast-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'broadcast.example.test',
        'ip_address' => '10.0.0.60',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    return Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'broadcast.example.test',
        'aliases' => [],
        'webroot' => '/var/www/broadcast.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => 'git',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'docker_compose_path' => 'docker-compose.yml',
        'status' => SiteStatus::PROVISIONING->value,
    ]);
}
