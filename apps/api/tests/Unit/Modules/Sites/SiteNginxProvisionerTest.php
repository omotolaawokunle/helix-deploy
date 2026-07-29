<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteNginxProvisioner;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Str;

it('creates webroot directories with sudo ownership', function (): void {
    [$server, $fake] = siteNginxProvisionerFixture();

    $fake->addSequence('sudo mkdir -p *',
        new SSHResult('sudo mkdir -p', 0, '', '', 0.01),
        new SSHResult('sudo mkdir -p', 0, '', '', 0.01),
    );
    $fake->addSequence('sudo chown -R *',
        new SSHResult('sudo chown', 0, '', '', 0.01),
        new SSHResult('sudo chown', 0, '', '', 0.01),
    );

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);

    $provisioner = new SiteNginxProvisioner($manager, app(CredentialVault::class));
    $provisioner->createWebroot($server, 'nolbzdesign.com');

    $fake->assertCommandExecuted("sudo mkdir -p *'/var/www/nolbzdesign.com'/releases*");
    $fake->assertCommandExecuted('*ln -sfn*');
    $fake->assertCommandExecuted("sudo chown -R 'deploy:www-data' '/var/www/nolbzdesign.com'*");
    $fake->assertCommandExecuted('*shared/storage/logs*');
});

it('owns webroot as the server ssh user when not deploy', function (): void {
    [$server, $fake] = siteNginxProvisionerFixture(sshUser: 'cursor');

    $fake->addSequence('sudo mkdir -p *',
        new SSHResult('sudo mkdir -p', 0, '', '', 0.01),
        new SSHResult('sudo mkdir -p', 0, '', '', 0.01),
    );
    $fake->addSequence('sudo chown -R *',
        new SSHResult('sudo chown', 0, '', '', 0.01),
        new SSHResult('sudo chown', 0, '', '', 0.01),
    );

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);

    $provisioner = new SiteNginxProvisioner($manager, app(CredentialVault::class));
    $provisioner->createWebroot($server, 'nolbzdesign.com');

    $fake->assertCommandExecuted("sudo chown -R 'cursor:www-data' '/var/www/nolbzdesign.com'*");
});

it('bootstraps current as a symlink for pre-deploy ssl webroot', function (): void {
    [$server, $fake] = siteNginxProvisionerFixture();

    $fake->addSequence('sudo mkdir -p *',
        new SSHResult('sudo mkdir -p', 0, '', '', 0.01),
        new SSHResult('sudo mkdir -p', 0, '', '', 0.01),
    );
    $fake->addSequence('sudo chown -R *',
        new SSHResult('sudo chown', 0, '', '', 0.01),
        new SSHResult('sudo chown', 0, '', '', 0.01),
    );

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->never();

    $provisioner = new SiteNginxProvisioner($manager, app(CredentialVault::class));
    $provisioner->ensureBootstrapCurrent($fake, '/var/www/nolbzdesign.com');

    $commands = $fake->getExecutedCommands();
    expect($commands[0])->toContain("'/var/www/nolbzdesign.com/releases/.helix-bootstrap'")
        ->and($commands[0])->toContain("ln -sfn '/var/www/nolbzdesign.com/releases/.helix-bootstrap' '/var/www/nolbzdesign.com/current'")
        ->and($commands[0])->toContain('.well-known/acme-challenge')
        ->and($commands[1])->toContain('/shared/storage/logs');
});

it('uploads nginx config via temp file then installs with sudo', function (): void {
    [$server, $fake, $site] = siteNginxProvisionerFixture(withSite: true);

    $fake->addResponse('sudo cp *', new SSHResult('sudo cp', 0, '', '', 0.01));
    $fake->addResponse('sudo nginx -t', new SSHResult('sudo nginx -t', 0, 'syntax is ok', '', 0.01));
    $fake->addResponse('sudo systemctl reload nginx', new SSHResult('sudo systemctl reload nginx', 0, '', '', 0.01));

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);

    $config = 'server { listen 80; server_name nolbzdesign.com; }';
    $provisioner = new SiteNginxProvisioner($manager, app(CredentialVault::class));
    $provisioner->apply($server, $site, $config);

    expect($fake->getUploads())->toHaveKey('/tmp/helix-nginx-nolbzdesign.com.conf')
        ->and($fake->getUploads()['/tmp/helix-nginx-nolbzdesign.com.conf'])->toBe($config);

    $fake->assertCommandExecuted('sudo cp */tmp/helix-nginx-nolbzdesign.com.conf*');
    $fake->assertCommandExecuted('sudo nginx -t');
    $fake->assertCommandExecuted('sudo systemctl reload nginx');
});

/**
 * @return array{0: Server, 1: FakeSSHConnection, 2?: Site}
 */
function siteNginxProvisionerFixture(bool $withSite = false, string $sshUser = 'deploy'): array
{
    $organization = Organization::query()->create([
        'name' => 'Nginx Provisioner Org',
        'slug' => 'nginx-prov-'.Str::random(6),
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
        'hostname' => 'nginx-prov.example.test',
        'ip_address' => '10.0.0.91',
        'ssh_port' => 22,
        'ssh_user' => $sshUser,
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $fake = (new FakeSSHConnection())->connect();

    if (! $withSite) {
        return [$server, $fake];
    }

    $site = Site::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'nolbzdesign.com',
        'aliases' => [],
        'webroot' => '/var/www/nolbzdesign.com/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => DeployMode::GIT->value,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::PROVISIONING->value,
    ]);

    return [$server, $fake, $site];
}
