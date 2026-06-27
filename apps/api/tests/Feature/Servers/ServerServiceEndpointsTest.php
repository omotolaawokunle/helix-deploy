<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Jobs\RunServerServiceOperationJob;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('lists installed services with cached status', function (): void {
    [$server, $owner] = serverServiceApiFixture([
        'nginx' => [
            'installed' => true,
            'status' => 'running',
            'statusCheckedAt' => '2026-06-13T10:00:00Z',
            'version' => '1.24',
        ],
        'nodejs' => [
            'installed' => true,
        ],
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/services")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.key', 'nginx')
        ->assertJsonPath('data.0.status', 'running')
        ->assertJsonPath('data.0.controllable', true)
        ->assertJsonPath('data.0.version', '1.24')
        ->assertJsonPath('data.1.key', 'nodejs')
        ->assertJsonPath('data.1.controllable', false);
});

it('queues service status sync', function (): void {
    Queue::fake();

    [$server, $owner] = serverServiceApiFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/services/sync-status")
        ->assertAccepted()
        ->assertJsonPath('message', 'Service status sync has been queued.');

    Queue::assertPushed(RunServerServiceOperationJob::class, function (RunServerServiceOperationJob $job) use ($server): bool {
        return $job->operation === 'sync-status'
            && $job->serverId === (string) $server->getKey();
    });
});

it('queues start stop and restart operations', function (): void {
    Queue::fake();

    [$server, $owner] = serverServiceApiFixture([
        'nginx' => ['installed' => true],
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/services/nginx/start")
        ->assertAccepted();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/services/nginx/stop")
        ->assertAccepted();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/services/nginx/restart")
        ->assertAccepted();

    Queue::assertPushed(RunServerServiceOperationJob::class, 3);
});

it('forbids cross organization service access', function (): void {
    [$server] = serverServiceApiFixture();

    $otherOrg = Organization::query()->create([
        'name' => 'Other Service Org',
        'slug' => 'other-service-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $otherOrg->generateAndStoreMasterKey();

    $otherOwner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $otherOrg->getKey(),
    ]);
    $otherOrg->users()->attach($otherOwner->getKey(), ['role' => TeamRole::OWNER->value]);

    $this->actingAs($otherOwner)
        ->getJson("/api/v1/servers/{$server->id}/services")
        ->assertForbidden();
});

it('forbids developers from managing services', function (): void {
    Queue::fake();

    [$server, $owner, $organization] = serverServiceApiFixture([
        'nginx' => ['installed' => true],
    ], true);

    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $this->actingAs($developer)
        ->postJson("/api/v1/servers/{$server->id}/services/nginx/restart")
        ->assertForbidden();

    $this->actingAs($developer)
        ->getJson("/api/v1/servers/{$server->id}/services")
        ->assertOk();
});

it('forbids service management on observe mode servers', function (): void {
    Queue::fake();

    [$server, $owner] = serverServiceApiFixture([
        'nginx' => ['installed' => true],
    ]);
    $server->forceFill(['management_mode' => 'observe'])->save();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/services/nginx/start")
        ->assertForbidden();
});

/**
 * @param array<string, array<string, mixed>> $installedServices
 * @return array{0: Server, 1: User}|array{0: Server, 1: User, 2: Organization}
 */
function serverServiceApiFixture(array $installedServices = [], bool $returnOrganization = false): array
{
    $organization = Organization::query()->create([
        'name' => 'Server Service Org',
        'slug' => 'server-service-'.Str::random(6),
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
        'hostname' => 'server-service.test',
        'ip_address' => '10.0.0.57',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => $installedServices,
        'credential_id' => null,
    ]);

    if ($returnOrganization) {
        return [$server, $owner, $organization];
    }

    return [$server, $owner];
}
