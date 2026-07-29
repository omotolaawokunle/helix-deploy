<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\CronJobs\Jobs\SyncCronJobsJob;
use App\Modules\CronJobs\Models\CronJob;
use App\Modules\Daemons\Jobs\RunDaemonOperationJob;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('queues horizon daemon and scheduler cron for a php site', function (): void {
    Queue::fake();

    [$site, $owner] = laravelWorkersEndpointFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/laravel-workers", [
            'workerType' => 'horizon',
        ])
        ->assertAccepted()
        ->assertJsonPath('message', 'Laravel worker setup has been queued.');

    Queue::assertPushed(RunDaemonOperationJob::class, function (RunDaemonOperationJob $job): bool {
        return $job->operation === 'create'
            && $job->dto !== null
            && $job->dto->name === 'example-test-horizon'
            && $job->dto->command === 'php artisan horizon'
            && $job->dto->directory === '/var/www/example.test/current';
    });

    Queue::assertPushed(SyncCronJobsJob::class);

    expect(CronJob::query()->where('command', 'cd /var/www/example.test/current && php artisan schedule:run >> /dev/null 2>&1')->exists())
        ->toBeTrue();

    expect(AuditLog::query()->where('operation', 'site.laravel_workers_setup')->exists())->toBeTrue();
});

it('queues queue worker daemon and scheduler cron for a php site', function (): void {
    Queue::fake();

    [$site, $owner] = laravelWorkersEndpointFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/laravel-workers", [
            'workerType' => 'queue',
        ])
        ->assertAccepted();

    Queue::assertPushed(RunDaemonOperationJob::class, function (RunDaemonOperationJob $job): bool {
        return $job->operation === 'create'
            && $job->dto !== null
            && $job->dto->name === 'example-test-queue'
            && str_contains($job->dto->command, 'queue:work');
    });
});

it('returns 409 when daemon already exists', function (): void {
    Queue::fake();

    [$site, $owner] = laravelWorkersEndpointFixture();

    SupervisorProcess::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $site->server_id,
        'organization_id' => (string) $site->organization_id,
        'name' => 'example-test-horizon',
        'command' => 'php artisan horizon',
        'directory' => '/var/www/example.test/current',
        'user' => 'www-data',
        'processes' => 1,
        'status' => 'running',
        'config_path' => '/etc/supervisor/conf.d/example-test-horizon.conf',
        'created_by' => (string) $owner->getKey(),
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/laravel-workers", [
            'workerType' => 'horizon',
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'A daemon named [example-test-horizon] already exists on this server.');

    Queue::assertNothingPushed();
});

it('returns 409 when scheduler cron already exists', function (): void {
    Queue::fake();

    [$site, $owner] = laravelWorkersEndpointFixture();

    CronJob::query()->create([
        'server_id' => (string) $site->server_id,
        'organization_id' => (string) $site->organization_id,
        'expression' => '* * * * *',
        'command' => 'cd /var/www/example.test/current && php artisan schedule:run >> /dev/null 2>&1',
        'user' => 'www-data',
        'active' => true,
        'created_by' => (string) $owner->getKey(),
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/laravel-workers", [
            'workerType' => 'horizon',
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'A scheduler cron job for this site path already exists on this server.');

    Queue::assertNothingPushed();
});

it('returns 422 for non php sites', function (): void {
    [$site, $owner] = laravelWorkersEndpointFixture(runtime: Runtime::DOCKER);

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/laravel-workers", [
            'workerType' => 'horizon',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Laravel workers can only be configured for PHP sites.');
});

it('validates worker type', function (): void {
    [$site, $owner] = laravelWorkersEndpointFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/laravel-workers", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['workerType']);
});

it('forbids setup for developer role', function (): void {
    [$site, , $developer] = laravelWorkersEndpointFixture(withDeveloper: true);

    $this->actingAs($developer)
        ->postJson("/api/v1/sites/{$site->id}/laravel-workers", [
            'workerType' => 'horizon',
        ])
        ->assertForbidden();
});

it('forbids cross organization setup', function (): void {
    [$site] = laravelWorkersEndpointFixture();

    $otherOrg = Organization::query()->create([
        'name' => 'Other Laravel Workers Org',
        'slug' => 'other-laravel-workers-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $otherOrg->generateAndStoreMasterKey();

    $intruder = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $otherOrg->getKey(),
    ]);
    $otherOrg->users()->attach($intruder->getKey(), ['role' => TeamRole::OWNER->value]);

    $this->actingAs($intruder)
        ->postJson("/api/v1/sites/{$site->id}/laravel-workers", [
            'workerType' => 'horizon',
        ])
        ->assertForbidden();
});

/**
 * @return array{0: Site, 1: User, 2?: User}
 */
function laravelWorkersEndpointFixture(
    Runtime $runtime = Runtime::PHP,
    bool $withDeveloper = false,
): array {
    $organization = Organization::query()->create([
        'name' => 'Laravel Workers Endpoint Org',
        'slug' => 'laravel-workers-endpoint-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $developer = null;
    if ($withDeveloper) {
        $developer = User::factory()->create([
            'email_verified_at' => now(),
            'current_organization_id' => (string) $organization->getKey(),
        ]);
        $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);
    }

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'laravel-workers-endpoint.test',
        'ip_address' => '10.0.0.55',
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
        'domain' => 'example.test',
        'aliases' => [],
        'webroot' => '/var/www/example.test/current/public',
        'runtime' => $runtime,
        'deploy_mode' => DeployMode::GIT,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE,
    ]);

    if ($withDeveloper && $developer !== null) {
        return [$site, $owner, $developer];
    }

    return [$site, $owner];
}
