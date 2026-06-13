<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Jobs\ApplyEnvVarsPullJob;
use App\Modules\Sites\Jobs\FetchEnvVarsPullPreviewJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('returns loading status and queues env var pull preview', function (): void {
    Queue::fake();

    [$site, $owner] = envVarPullApiFixture();

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/env-vars/pull")
        ->assertOk()
        ->assertJsonPath('data.status', 'loading');

    Queue::assertPushed(FetchEnvVarsPullPreviewJob::class);
});

it('returns cached env var pull preview without secret values', function (): void {
    [$site, $owner] = envVarPullApiFixture();

    Cache::put(FetchEnvVarsPullPreviewJob::cacheKey((string) $site->getKey()), [
        'status' => 'ready',
        'diff' => [
            'serverFileExists' => true,
            'new' => ['NEW_KEY'],
            'changed' => ['APP_KEY'],
            'unchanged' => [],
            'helixOnly' => [],
            'skipped' => [],
        ],
    ], now()->addMinutes(5));

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/env-vars/pull")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.new.0', 'NEW_KEY')
        ->assertJsonMissingPath('data.value')
        ->assertJsonMissingPath('data.APP_KEY');
});

it('queues env var pull apply with strategy', function (): void {
    Queue::fake();

    [$site, $owner] = envVarPullApiFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/env-vars/pull", [
            'strategy' => 'add_new',
        ])
        ->assertAccepted();

    Queue::assertPushed(ApplyEnvVarsPullJob::class, function (ApplyEnvVarsPullJob $job) use ($site): bool {
        return $job->siteId === (string) $site->getKey()
            && $job->strategy->value === 'add_new';
    });
});

it('forbids developers from pulling env vars', function (): void {
    [$site, $owner, $organization] = envVarPullApiFixture();

    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $this->actingAs($developer)
        ->getJson("/api/v1/sites/{$site->id}/env-vars/pull")
        ->assertForbidden();
});

/**
 * @return array{0: Site, 1: User, 2: Organization}
 */
function envVarPullApiFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Env Pull API Org',
        'slug' => 'env-pull-api-'.Str::random(6),
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
        'hostname' => 'env-pull-api.test',
        'ip_address' => '10.0.0.73',
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
        'domain' => 'pull-api.example.test',
        'aliases' => [],
        'webroot' => '/var/www/pull-api.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => DeployMode::GIT->value,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE->value,
    ]);

    return [$site, $owner, $organization];
}
