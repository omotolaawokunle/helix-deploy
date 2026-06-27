<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Jobs\AdoptServerSslCertificatesJob;
use App\Modules\Sites\Jobs\RenewServerSslCertificatesJob;
use App\Modules\Sites\Jobs\SyncServerSslCertificatesJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @return array{0: Organization, 1: User, 2: Server, 3: Site}
 */
function serverSslEndpointFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Server SSL Org',
        'slug' => 'server-ssl-org-'.Str::random(6),
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
        'hostname' => 'ssl-server.example.test',
        'ip_address' => '10.0.0.80',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => ['certbot' => ['installed' => true]],
    ]);

    $site = Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'app-'.Str::random(4).'.example.test',
        'aliases' => [],
        'webroot' => '/var/www/app.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => 'git',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'docker_compose_path' => 'docker-compose.yml',
        'status' => 'active',
        'enable_ssl' => true,
        'ssl_status' => SslStatus::ACTIVE->value,
        'ssl_expires_at' => now()->addDays(45),
        'ssl_checked_at' => now(),
    ]);

    return [$organization, $owner, $server, $site];
}

it('returns ssl certificate overview for a server', function (): void {
    [, $owner, $server] = serverSslEndpointFixture();

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/ssl-certificates")
        ->assertOk()
        ->assertJsonPath('data.activeCertificateCount', 1)
        ->assertJsonPath('data.hasCertbot', true)
        ->assertJsonCount(1, 'data.certificates');
});

it('queues sync when ssl checked_at is stale', function (): void {
    Queue::fake();

    [, $owner, $server, $site] = serverSslEndpointFixture();

    $site->forceFill([
        'ssl_checked_at' => now()->subDays(2),
    ])->save();

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/ssl-certificates")
        ->assertStatus(202)
        ->assertJsonPath('data.syncQueued', true);

    Queue::assertPushed(SyncServerSslCertificatesJob::class);
});

it('queues manual ssl sync', function (): void {
    Queue::fake();

    [, $owner, $server] = serverSslEndpointFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/ssl-certificates/sync")
        ->assertStatus(202);

    Queue::assertPushed(SyncServerSslCertificatesJob::class);
});

it('queues manual ssl renewal for server sites', function (): void {
    Queue::fake();

    [, $owner, $server] = serverSslEndpointFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/ssl-certificates/renew")
        ->assertStatus(202);

    Queue::assertPushed(RenewServerSslCertificatesJob::class);
});

it('queues manual ssl adoption for server sites', function (): void {
    Queue::fake();

    [, $owner, $server] = serverSslEndpointFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/ssl-certificates/adopt")
        ->assertStatus(202);

    Queue::assertPushed(AdoptServerSslCertificatesJob::class);
});

it('forbids ssl management on observe mode servers', function (): void {
    [, $owner, $server] = serverSslEndpointFixture();

    $server->forceFill(['management_mode' => 'observe'])->save();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/ssl-certificates/sync")
        ->assertForbidden();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/ssl-certificates/adopt")
        ->assertForbidden();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/ssl-certificates/renew")
        ->assertForbidden();
});

it('forbids cross organization ssl certificate access', function (): void {
    [, , $server] = serverSslEndpointFixture();

    $otherOrg = Organization::query()->create([
        'name' => 'Other SSL Org',
        'slug' => 'other-ssl-org-'.Str::random(6),
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
        ->getJson("/api/v1/servers/{$server->id}/ssl-certificates")
        ->assertForbidden();
});

it('includes ssl summary on organization server list', function (): void {
    [, $owner, $server] = serverSslEndpointFixture();

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$server->organization_id}/servers")
        ->assertOk()
        ->assertJsonPath('data.0.sslSummary.activeCount', 1)
        ->assertJsonPath('data.0.sslSummary.expiringSoonCount', 0);
});
