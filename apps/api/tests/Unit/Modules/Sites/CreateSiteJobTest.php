<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Actions\CreateSiteAction;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Jobs\CreateSiteJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('dispatches create site job on the provisioning queue', function (): void {
    Queue::fake();

    $job = new CreateSiteJob(
        siteId: (string) Str::uuid(),
        actorId: (string) Str::uuid(),
    );

    dispatch($job);

    Queue::assertPushedOn('provisioning', CreateSiteJob::class);
});

it('provisions site via create site action', function (): void {
    [$organization, $owner, $server, $site] = createSiteJobFixture();

    $action = \Mockery::mock(CreateSiteAction::class);
    $action->shouldReceive('provision')
        ->once()
        ->with(\Mockery::on(fn (Site $passedSite): bool => (string) $passedSite->getKey() === (string) $site->getKey()));

    $job = new CreateSiteJob(
        siteId: (string) $site->getKey(),
        actorId: (string) $owner->getKey(),
    );

    $job->handle($action);
});

/**
 * @return array{Organization, User, Server, Site}
 */
function createSiteJobFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Site Job Org',
        'slug' => 'site-job-org-'.Str::random(6),
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
        'hostname' => 'site-job.example.test',
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
        'domain' => 'site-job.example.test',
        'aliases' => [],
        'webroot' => '/var/www/site-job.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => 'git',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'docker_compose_path' => 'docker-compose.yml',
        'status' => SiteStatus::PROVISIONING->value,
    ]);

    return [$organization, $owner, $server, $site];
}
