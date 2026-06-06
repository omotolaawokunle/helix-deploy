<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Integrations\Services\CloudflareConnectionService;
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
        app(CloudflareConnectionService::class),
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
        app(CloudflareConnectionService::class),
    );

    $provisioner->issue($site);

    $site->refresh();

    expect($site->ssl_status)->toBe(SslStatus::FAILED)
        ->and($site->ssl_error)->toContain('challenge failed');

    expect(AuditLog::query()->where('operation', 'site.ssl_certificate.failed')->exists())->toBeTrue();
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
