<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Actions\CreateSiteAction;
use App\Modules\Sites\DTOs\CreateSiteDTO;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Events\SiteCreated;
use App\Modules\Sites\Events\SiteProvisioningFailed;
use App\Modules\Sites\Exceptions\NginxConfigInvalidException;
use Illuminate\Support\Facades\Event;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\NginxConfigGenerator;
use App\Modules\Sites\Services\SiteNginxProvisioner;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

it('creates a site record in provisioning status without running ssh', function (): void {
    [$organization, $owner, $server] = createSiteActionFixture();

    $provisioner = \Mockery::mock(SiteNginxProvisioner::class);
    $provisioner->shouldNotReceive('createWebroot');

    $action = new CreateSiteAction(new NginxConfigGenerator(), $provisioner);

    $site = $action->execute(
        $server,
        $organization,
        $owner,
        createSiteDto('pending.example.test', Runtime::PHP, '8.3'),
    );

    expect($site->status)->toBe(SiteStatus::PROVISIONING);
});

it('dispatches site created event when provision succeeds', function (): void {
    Event::fake([SiteCreated::class]);

    [$organization, $owner, $server] = createSiteActionFixture();

    $provisioner = \Mockery::mock(SiteNginxProvisioner::class);
    $provisioner->shouldReceive('createWebroot')->once();
    $provisioner->shouldReceive('apply')->once();

    $action = new CreateSiteAction(new NginxConfigGenerator(), $provisioner);

    $site = $action->execute(
        $server,
        $organization,
        $owner,
        createSiteDto('ready.example.test', Runtime::PHP, '8.3'),
    );

    $action->provision($site);

    Event::assertDispatched(SiteCreated::class);
});

it('rolls back site record when nginx test fails during provision', function (): void {
    Event::fake([SiteProvisioningFailed::class]);

    [$organization, $owner, $server] = createSiteActionFixture();

    $provisioner = \Mockery::mock(SiteNginxProvisioner::class);
    $provisioner->shouldReceive('createWebroot')->once();
    $provisioner->shouldReceive('apply')->once()->andThrow(
        new NginxConfigInvalidException('rollback.example.test', 'syntax error'),
    );
    $provisioner->shouldReceive('rollbackConfig')->once();

    $action = new CreateSiteAction(new NginxConfigGenerator(), $provisioner);

    $site = $action->execute(
        $server,
        $organization,
        $owner,
        createSiteDto('rollback.example.test', Runtime::PHP, '8.3'),
    );

    expect(fn () => $action->provision($site))->toThrow(NginxConfigInvalidException::class);

    expect(Site::query()->where('domain', 'rollback.example.test')->exists())->toBeFalse();

    Event::assertDispatched(SiteProvisioningFailed::class);
});

it('forbids creating a site on another organizations server', function (): void {
    [$organization, $owner] = createSiteActionFixture(withServer: false);

    $otherOrganization = Organization::query()->create([
        'name' => 'Other Sites Org',
        'slug' => 'other-sites-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $otherOrganization->generateAndStoreMasterKey();

    $foreignServer = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $otherOrganization->getKey(),
        'hostname' => 'foreign.example.test',
        'ip_address' => '10.0.0.99',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $provisioner = \Mockery::mock(SiteNginxProvisioner::class);
    $action = new CreateSiteAction(new NginxConfigGenerator(), $provisioner);

    expect(fn () => $action->execute(
        $foreignServer,
        $organization,
        $owner,
        createSiteDto('cross-org.example.test', Runtime::PHP, '8.3'),
    ))->toThrow(AuthorizationException::class);
});

/**
 * @return array{Organization, User, Server|null}
 */
function createSiteActionFixture(bool $withServer = true): array
{
    $organization = Organization::query()->create([
        'name' => 'Site Action Org',
        'slug' => 'site-action-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = null;

    if ($withServer) {
        $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
            'organization_id' => (string) $organization->getKey(),
            'hostname' => 'site-action.example.test',
            'ip_address' => '10.0.0.21',
            'ssh_port' => 22,
            'ssh_user' => 'deploy',
            'provider' => 'generic',
            'status' => 'active',
            'management_mode' => 'managed',
            'created_by' => (string) $owner->getKey(),
            'tags' => [],
            'installed_services' => [],
        ]);
    }

    return [$organization, $owner, $server];
}

function createSiteDto(string $domain, Runtime $runtime, ?string $phpVersion = null): CreateSiteDTO
{
    return new CreateSiteDTO(
        domain: $domain,
        aliases: [],
        webroot: '/var/www/'.$domain.'/current/public',
        runtime: $runtime,
        deployMode: DeployMode::GIT,
        repositoryUrl: null,
        repositoryProvider: null,
        deployBranch: 'main',
        deployScript: null,
        runMigrations: true,
        dockerImage: null,
        dockerRegistry: null,
        dockerComposePath: 'docker-compose.yml',
        dockerBuildMode: null,
        phpVersion: $phpVersion,
        nodePm: null,
        pythonWsgi: null,
        goBinaryPath: null,
        goServiceName: null,
        appPort: null,
        projectId: null,
        environmentId: null,
        pipelineId: null,
    );
}
