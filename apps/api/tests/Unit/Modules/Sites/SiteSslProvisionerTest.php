<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Integrations\Services\DnsProviderRegistry;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\NginxConfigGenerator;
use App\Modules\Sites\Services\SiteNginxProvisioner;
use App\Modules\Sites\Services\SiteSslProvisioner;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use Tests\Support\SSH\PendingFakeSshConnection;
use Illuminate\Support\Str;

it('issues a lets encrypt certificate via webroot and updates nginx', function (): void {
    [$site, $fake] = siteSslProvisionerFixture();

    $fake->addResponse('*command -v certbot*', sshSuccess('yes'));
    $fake->addResponse('*certbot certonly*', sshSuccess());

    mockSiteSslSshManager($fake);

    $nginxProvisioner = \Mockery::mock(SiteNginxProvisioner::class);
    $nginxProvisioner->shouldReceive('apply')->once();

    $provisioner = new SiteSslProvisioner(
        app(SSHManager::class),
        app(\App\Modules\Credentials\CredentialVault::class),
        new NginxConfigGenerator(),
        $nginxProvisioner,
        app(DnsProviderRegistry::class),
    );

    $provisioner->issue($site);

    $site->refresh();

    expect($site->ssl_status)->toBe(SslStatus::ACTIVE)
        ->and($site->ssl_error)->toBeNull();

    $fake->assertCommandExecuted('*certbot certonly*');

    expect(AuditLog::query()->where('operation', 'site.ssl_certificate.issued')->exists())->toBeTrue();
});

it('marks ssl as failed when certbot fails', function (): void {
    [$site, $fake] = siteSslProvisionerFixture();

    $fake->addResponse('*command -v certbot*', sshSuccess('yes'));
    $fake->addResponse('*certbot certonly*', sshFailure('challenge failed'));

    mockSiteSslSshManager($fake);

    $nginxProvisioner = \Mockery::mock(SiteNginxProvisioner::class);
    $nginxProvisioner->shouldNotReceive('apply');

    $provisioner = new SiteSslProvisioner(
        app(SSHManager::class),
        app(\App\Modules\Credentials\CredentialVault::class),
        new NginxConfigGenerator(),
        $nginxProvisioner,
        app(DnsProviderRegistry::class),
    );

    $provisioner->issue($site);

    $site->refresh();

    expect($site->ssl_status)->toBe(SslStatus::FAILED)
        ->and($site->ssl_error)->toContain('challenge failed');

    expect(AuditLog::query()->where('operation', 'site.ssl_certificate.failed')->exists())->toBeTrue();
});

it('issues a lets encrypt certificate via digitalocean dns-01', function (): void {
    [$site, $fake] = siteSslProvisionerFixture();

    $ownerId = \App\Models\User::query()->where('current_organization_id', $site->organization_id)->value('id');

    $projectDnsZone = \App\Modules\Integrations\Models\ProjectDnsZone::query()->create([
        'organization_id' => (string) $site->organization_id,
        'project_id' => \App\Modules\Projects\Models\Project::query()->create([
            'organization_id' => (string) $site->organization_id,
            'name' => 'SSL DO Project',
            'slug' => 'ssl-do-project-'.\Illuminate\Support\Str::random(6),
            'description' => null,
            'repository_url' => null,
        ])->getKey(),
        'dns_provider' => \App\Modules\Integrations\Enums\DnsProvider::DIGITALOCEAN->value,
        'zone_id' => 'do-example.test',
        'base_domain' => 'do-example.test',
        'assigned_by' => null,
    ]);

    $site->forceFill([
        'ssl_challenge' => \App\Modules\Sites\Enums\SslChallenge::DNS_01->value,
        'dns_provider' => \App\Modules\Integrations\Enums\DnsProvider::DIGITALOCEAN->value,
        'project_dns_zone_id' => (string) $projectDnsZone->getKey(),
    ])->save();

    $credential = \App\Modules\Credentials\Models\Credential::query()->create([
        'organization_id' => (string) $site->organization_id,
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'dns_provider_credential',
        'name' => \App\Modules\Integrations\Enums\DnsProvider::DIGITALOCEAN->credentialName(),
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => null,
        'created_by' => (string) $ownerId,
        'last_used_at' => null,
    ]);

    \App\Modules\Integrations\Models\DigitalOceanDnsConnection::query()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => (string) $site->organization_id,
        'credential_id' => (string) $credential->getKey(),
        'status' => \App\Modules\Integrations\Enums\CloudflareConnectionStatus::CONNECTED->value,
        'connected_by' => null,
    ]);

    $fake->addResponse('*command -v certbot*', sshSuccess('yes'));
    $fake->addResponse('*chmod 600*', sshSuccess());
    $fake->addResponse('*certbot certonly --dns-digitalocean*', sshSuccess());
    $fake->addResponse('*rm -f*', sshSuccess());

    mockSiteSslSshManager($fake);

    $vault = \Mockery::mock(\App\Modules\Credentials\CredentialVault::class);
    $vault->shouldReceive('getDnsProviderCredential')->andReturn('do-test-token');

    $registry = new DnsProviderRegistry(
        $vault,
        app(\App\Modules\Integrations\Contracts\CloudflareClientInterface::class),
        new \App\Modules\Integrations\Services\DigitalOcean\DigitalOceanDnsClient(),
        app(\App\Modules\Integrations\Services\CloudflareConnectionService::class),
        app(\App\Modules\Integrations\Services\DigitalOceanConnectionService::class),
    );

    $nginxProvisioner = \Mockery::mock(\App\Modules\Sites\Services\SiteNginxProvisioner::class);
    $nginxProvisioner->shouldReceive('apply')->once();

    $provisioner = new SiteSslProvisioner(
        app(SSHManager::class),
        app(\App\Modules\Credentials\CredentialVault::class),
        new NginxConfigGenerator(),
        $nginxProvisioner,
        $registry,
    );

    $provisioner->issue($site->refresh());

    $site->refresh();

    expect($site->ssl_status)->toBe(SslStatus::ACTIVE);
    $fake->assertCommandExecuted('*certbot certonly --dns-digitalocean*');
});

function mockSiteSslSshManager(FakeSSHConnection $fake): void
{
    $pending = new PendingFakeSshConnection($fake);

    test()->mock(SSHManager::class, function ($mock) use ($pending): void {
        $mock->shouldReceive('connect')->andReturn($pending);
    });
}

/**
 * @return array{Site, FakeSSHConnection}
 */
function siteSslProvisionerFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'SSL Provisioner Org',
        'slug' => 'ssl-provisioner-org-'.Str::random(6),
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
        'hostname' => 'ssl.example.test',
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

    $site = Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'secure.example.test',
        'aliases' => [],
        'webroot' => '/var/www/secure.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => 'git',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'docker_compose_path' => 'docker-compose.yml',
        'status' => 'provisioning',
        'enable_ssl' => true,
        'ssl_status' => SslStatus::PENDING->value,
    ]);

    return [$site, (new FakeSSHConnection())->connect()];
}
