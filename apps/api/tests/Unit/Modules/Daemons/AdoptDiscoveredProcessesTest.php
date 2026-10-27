<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CronJobs\Models\CronJob;
use App\Modules\CronJobs\Services\CronService;
use App\Modules\Daemons\Enums\DaemonStatus;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\DTOs\DiscoveredCronSnapshot;
use App\Modules\Servers\DTOs\DiscoveredDaemonSnapshot;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Str;

it('adopts supervisor programs and external crontab lines for the matching site', function (): void {
    [$server, $site] = adoptedProcessFixture();

    $sitePaths = app(\App\Modules\Sites\Contracts\SiteCurrentPathIndexInterface::class)
        ->forServer((string) $server->getKey(), (string) $server->organization_id);

    $daemons = app(\App\Modules\Daemons\Contracts\DiscoveredDaemonImporterInterface::class)->import($server, [
        new DiscoveredDaemonSnapshot(
            name: 'laravel_horizon',
            command: '/usr/bin/php /var/www/live/current/artisan horizon --no-interaction',
            directory: '/var/www/live/current',
            user: 'deploy',
            processes: 1,
            configPath: '/etc/supervisor/conf.d/horizon.conf',
            status: 'running',
        ),
        new DiscoveredDaemonSnapshot(
            name: 'reverb',
            command: '/usr/bin/php /var/www/live/current/artisan reverb:start --no-interaction',
            directory: '/var/www/live/current',
            user: 'deploy',
            processes: 1,
            configPath: '/etc/supervisor/conf.d/reverb.conf',
            status: 'running',
        ),
    ], $sitePaths);

    $crons = app(\App\Modules\CronJobs\Contracts\DiscoveredCronImporterInterface::class)->import($server, [
        new DiscoveredCronSnapshot(
            expression: '* * * * *',
            command: 'cd /var/www/live/current && php artisan schedule:run >> /dev/null 2>&1',
            user: 'root',
        ),
    ], $sitePaths);

    expect($daemons)->toBe(['created' => 2, 'updated' => 0])
        ->and($crons)->toBe(['created' => 1, 'updated' => 0]);

    $horizon = SupervisorProcess::query()->withoutGlobalScope('owned_by_organization')->where('name', 'laravel_horizon')->first();
    $reverb = SupervisorProcess::query()->withoutGlobalScope('owned_by_organization')->where('name', 'reverb')->first();
    $cron = CronJob::query()->withoutGlobalScope('owned_by_organization')->first();

    expect($horizon?->site_id)->toBe((string) $site->getKey())
        ->and($horizon?->status)->toBe(DaemonStatus::RUNNING)
        ->and($reverb?->site_id)->toBe((string) $site->getKey())
        ->and($cron?->external)->toBeTrue()
        ->and($cron?->site_id)->toBe((string) $site->getKey())
        ->and($cron?->user)->toBe('root');

    $again = app(\App\Modules\Daemons\Contracts\DiscoveredDaemonImporterInterface::class)->import($server, [
        new DiscoveredDaemonSnapshot(
            name: 'laravel_horizon',
            command: '/usr/bin/php /var/www/live/current/artisan horizon --no-interaction',
            directory: '/var/www/live/current',
            user: 'deploy',
            processes: 1,
            configPath: '/etc/supervisor/conf.d/horizon.conf',
            status: 'running',
        ),
    ], $sitePaths);

    expect($again['created'])->toBe(0)
        ->and(SupervisorProcess::query()->withoutGlobalScope('owned_by_organization')->count())->toBe(2);

    $crontab = app(CronService::class)->buildCrontab($server);

    expect($crontab)->not->toContain('schedule:run');
});

it('does not mark an existing helix cron job as external', function (): void {
    [$server] = adoptedProcessFixture();
    $ownerId = (string) $server->created_by;

    CronJob::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $server->organization_id,
        'expression' => '* * * * *',
        'command' => 'cd /var/www/live/current && php artisan schedule:run >> /dev/null 2>&1',
        'user' => 'root',
        'active' => true,
        'external' => false,
        'created_by' => $ownerId,
    ]);

    app(\App\Modules\CronJobs\Contracts\DiscoveredCronImporterInterface::class)->import($server, [
        new DiscoveredCronSnapshot(
            expression: '* * * * *',
            command: 'cd /var/www/live/current && php artisan schedule:run >> /dev/null 2>&1',
            user: 'root',
        ),
    ], []);

    $cron = CronJob::query()->withoutGlobalScope('owned_by_organization')->first();

    expect(CronJob::query()->withoutGlobalScope('owned_by_organization')->count())->toBe(1)
        ->and($cron?->external)->toBeFalse();
});

/**
 * @return array{0: Server, 1: Site}
 */
function adoptedProcessFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Adopted Process Org',
        'slug' => 'adopted-process-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $owner = User::factory()->create();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'adopted-process.test',
        'ip_address' => '10.2.0.15',
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
        'domain' => 'getlectern.com',
        'aliases' => [],
        'webroot' => '/var/www/live/current/public',
        'runtime' => Runtime::PHP,
        'deploy_mode' => DeployMode::GIT,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE,
    ]);

    return [$server, $site];
}
