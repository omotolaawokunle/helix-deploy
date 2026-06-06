<?php

declare(strict_types=1);

use App\Modules\Integrations\Services\Cloudflare\CloudflareClient;
use Illuminate\Support\Facades\Http;

it('retries cloudflare requests after rate limiting', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-1/dns_records*' => Http::sequence()
            ->push(['success' => false, 'errors' => [['message' => 'Rate limited.']]], 429, ['Retry-After' => '1'])
            ->push([
                'success' => true,
                'result' => [
                    [
                        'id' => 'rec-123',
                        'name' => 'app.example.test',
                        'type' => 'A',
                        'content' => '10.0.0.10',
                        'proxied' => false,
                    ],
                ],
            ]),
    ]);

    $client = new CloudflareClient();
    $record = $client->findARecord('token', 'zone-1', 'app.example.test');

    expect($record)->not->toBeNull()
        ->and($record?->id)->toBe('rec-123');

    Http::assertSentCount(2);
});

it('adopts an existing matching cloudflare a record during dns provision', function (): void {
    [$site, $organization, $projectDnsZone] = siteDnsProvisionerFixture();

    $fakeClient = new \App\Modules\Integrations\Services\Cloudflare\FakeCloudflareClient();
    $fakeClient->seedExistingRecord(
        zoneId: (string) $projectDnsZone->zone_id,
        hostname: $site->domain,
        ipAddress: '10.0.0.40',
        recordId: 'cf-adopted-record',
    );

    $vault = \Mockery::mock(\App\Modules\Credentials\CredentialVault::class);
    $vault->shouldReceive('getDnsProviderCredential')->andReturn('fake-cloudflare-token');

    $registry = new \App\Modules\Integrations\Services\DnsProviderRegistry(
        $vault,
        $fakeClient,
        new \App\Modules\Integrations\Services\DigitalOcean\DigitalOceanDnsClient(),
        app(\App\Modules\Integrations\Services\CloudflareConnectionService::class),
        app(\App\Modules\Integrations\Services\DigitalOceanConnectionService::class),
    );

    $provisioner = new \App\Modules\Integrations\Services\SiteDnsProvisioner(
        $registry,
        app(\App\Modules\Integrations\Services\CloudflareHostnameResolver::class),
    );

    $provisioner->provision($site->refresh());

    $site->refresh();

    expect($site->dns_status)->toBe(\App\Modules\Integrations\Enums\DnsStatus::ACTIVE)
        ->and($site->dns_record_ids)->toBe(['cf-adopted-record'])
        ->and($fakeClient->createdRecords)->toBe([]);
});

/**
 * @return array{0: \App\Modules\Sites\Models\Site, 1: \App\Modules\Organizations\Models\Organization, 2: \App\Modules\Integrations\Models\ProjectDnsZone}
 */
function siteDnsProvisionerFixture(): array
{
    $organization = \App\Modules\Organizations\Models\Organization::query()->create([
        'name' => 'DNS Provisioner Org',
        'slug' => 'dns-provisioner-org-'.\Illuminate\Support\Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = \App\Models\User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => \App\Modules\Teams\Enums\TeamRole::OWNER->value]);

    $server = \App\Modules\Servers\Models\Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'dns-provision.example.test',
        'ip_address' => '10.0.0.40',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $project = \App\Modules\Projects\Models\Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'DNS Project',
        'slug' => 'dns-project-'.\Illuminate\Support\Str::random(6),
        'description' => null,
        'repository_url' => null,
    ]);

    $projectDnsZone = \App\Modules\Integrations\Models\ProjectDnsZone::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $project->getKey(),
        'dns_provider' => \App\Modules\Integrations\Enums\DnsProvider::CLOUDFLARE->value,
        'zone_id' => 'zone-dns-123',
        'base_domain' => 'secure.example.test',
        'assigned_by' => (string) $owner->getKey(),
    ]);

    $site = \App\Modules\Sites\Models\Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $project->getKey(),
        'project_dns_zone_id' => (string) $projectDnsZone->getKey(),
        'domain' => 'secure.example.test',
        'aliases' => [],
        'webroot' => '/var/www/secure.example.test/current/public',
        'runtime' => \App\Modules\Sites\Enums\Runtime::PHP->value,
        'deploy_mode' => 'git',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'docker_compose_path' => 'docker-compose.yml',
        'status' => 'active',
        'auto_create_dns' => true,
        'dns_zone_id' => 'zone-dns-123',
        'dns_status' => \App\Modules\Integrations\Enums\DnsStatus::PENDING->value,
        'dns_provider' => \App\Modules\Integrations\Enums\DnsProvider::CLOUDFLARE->value,
        'dns_record_ids' => [],
    ]);

    return [$site, $organization, $projectDnsZone];
}
