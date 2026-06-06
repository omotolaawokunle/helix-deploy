<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Services\BuildRunnerPool;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\NodePM;
use App\Modules\Sites\Enums\PythonWSGI;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

beforeEach(function (): void {
    useInMemoryRunnerSlotStore();
});

it('acquires and releases runner slots atomically', function (): void {
    $runner = createPoolTestRunner(maxConcurrentBuilds: 1);
    $slotManager = app(RunnerSlotManager::class);

    $firstSlot = $slotManager->acquire($runner, 'build-a');
    $secondSlot = $slotManager->acquire($runner, 'build-b');

    expect($firstSlot)->toBe(0)
        ->and($secondSlot)->toBeNull()
        ->and($slotManager->activeBuildCount($runner))->toBe(1)
        ->and($slotManager->availableSlots($runner))->toBe(0);

    $slotManager->release($runner, 0);

    expect($slotManager->activeBuildCount($runner))->toBe(0)
        ->and($slotManager->availableSlots($runner))->toBe(1);
});

it('releases slots by build id', function (): void {
    $runner = createPoolTestRunner(maxConcurrentBuilds: 2);
    $slotManager = app(RunnerSlotManager::class);

    $slotManager->acquire($runner, 'build-a');
    $slotManager->acquire($runner, 'build-b');

    expect($slotManager->activeBuildCount($runner))->toBe(2);

    $slotManager->releaseByBuildId($runner, 'build-a');

    expect($slotManager->activeBuildCount($runner))->toBe(1)
        ->and($slotManager->availableSlots($runner))->toBe(1);
});

it('selects the runner with the most free slots', function (): void {
    [$organization, , $site] = createPoolSiteFixture(Runtime::PHP);
    $pool = app(BuildRunnerPool::class);
    $slotManager = app(RunnerSlotManager::class);

    $busyRunner = createPoolTestRunner(
        organization: $organization,
        name: 'Busy Runner',
        ipAddress: '10.0.0.10',
        maxConcurrentBuilds: 2,
        supportedRuntimes: ['php'],
    );
    $freeRunner = createPoolTestRunner(
        organization: $organization,
        name: 'Free Runner',
        ipAddress: '10.0.0.11',
        maxConcurrentBuilds: 2,
        supportedRuntimes: ['php'],
    );

    $slotManager->acquire($busyRunner, 'build-1');
    $slotManager->acquire($busyRunner, 'build-2');

    $selected = $pool->acquire($site, $organization);

    expect($selected)->not->toBeNull()
        ->and((string) $selected?->getKey())->toBe((string) $freeRunner->getKey());
});

it('skips runners with incompatible runtimes', function (): void {
    [$organization, , $site] = createPoolSiteFixture(Runtime::NODEJS);
    $pool = app(BuildRunnerPool::class);

    createPoolTestRunner(
        organization: $organization,
        name: 'PHP Only',
        ipAddress: '10.0.0.20',
        supportedRuntimes: ['php'],
    );

    expect($pool->acquire($site, $organization))->toBeNull();
});

it('does not return project scoped runners for a different project', function (): void {
    [$organization, , $site] = createPoolSiteFixture(Runtime::PHP);
    $pool = app(BuildRunnerPool::class);

    $otherProject = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Other Project',
    ]);

    createPoolTestRunner(
        organization: $organization,
        name: 'Other Project Runner',
        ipAddress: '10.0.0.30',
        supportedRuntimes: ['php'],
        projectId: (string) $otherProject->getKey(),
    );

    expect($pool->acquire($site, $organization))->toBeNull();
});

it('prefers the site configured runner when it is available', function (): void {
    [$organization, , $site, $preferredRunner, $fallbackRunner] = createPreferredRunnerFixture();
    $pool = app(BuildRunnerPool::class);

    $selected = $pool->acquire($site, $organization);

    expect($selected)->not->toBeNull()
        ->and((string) $selected?->getKey())->toBe((string) $preferredRunner->getKey())
        ->and((string) $selected?->getKey())->not->toBe((string) $fallbackRunner->getKey());
});

/**
 * @return array{Organization, User, Site}
 */
function createPoolSiteFixture(Runtime $runtime): array
{
    $organization = Organization::query()->create([
        'name' => 'Pool Site Org',
        'slug' => 'pool-site-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $project = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Pool Project',
    ]);

    $environment = Environment::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $project->getKey(),
        'name' => 'production',
        'label' => 'Production',
        'is_production' => true,
    ]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'pool.example.test',
        'ip_address' => '127.0.0.1',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $site = Site::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $project->getKey(),
        'environment_id' => (string) $environment->getKey(),
        'domain' => 'pool.example.test',
        'aliases' => [],
        'webroot' => '/var/www/pool.example.test/current/public',
        'runtime' => $runtime,
        'deploy_mode' => DeployMode::GIT,
        'repository_url' => 'git@github.com:helix/pool.git',
        'repository_provider' => 'github',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'docker_compose_path' => 'docker-compose.yml',
        'node_pm' => NodePM::PM2,
        'python_wsgi' => PythonWSGI::GUNICORN,
        'status' => SiteStatus::ACTIVE,
    ]);

    return [$organization, $owner, $site];
}

/**
 * @return array{Organization, User, Site, BuildRunner, BuildRunner}
 */
function createPreferredRunnerFixture(): array
{
    [$organization, $owner, $site] = createPoolSiteFixture(Runtime::PHP);

    $preferredRunner = createPoolTestRunner(
        organization: $organization,
        name: 'Preferred Runner',
        ipAddress: '10.0.0.40',
        maxConcurrentBuilds: 1,
        supportedRuntimes: ['php'],
    );

    $fallbackRunner = createPoolTestRunner(
        organization: $organization,
        name: 'Fallback Runner',
        ipAddress: '10.0.0.41',
        maxConcurrentBuilds: 3,
        supportedRuntimes: ['php'],
    );

    $site->forceFill(['build_runner_id' => (string) $preferredRunner->getKey()])->save();

    return [$organization, $owner, $site->refresh(), $preferredRunner, $fallbackRunner];
}
