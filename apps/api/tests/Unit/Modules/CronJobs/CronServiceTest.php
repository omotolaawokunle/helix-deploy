<?php

declare(strict_types=1);

use App\Modules\CronJobs\Models\CronJob;
use App\Modules\CronJobs\Services\CronService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Models\User;
use Illuminate\Support\Str;

it('builds crontab with header and excludes disabled jobs', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Cron Service Org',
        'slug' => 'cron-service-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $owner = User::factory()->create();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'cron-service.test',
        'ip_address' => '10.0.0.22',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $active = CronJob::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'expression' => '0 0 * * *',
        'command' => 'php artisan schedule:run',
        'user' => 'www-data',
        'active' => true,
        'created_by' => (string) $owner->getKey(),
    ]);

    CronJob::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'expression' => '*/10 * * * *',
        'command' => 'php artisan queue:work',
        'user' => 'www-data',
        'active' => false,
        'created_by' => (string) $owner->getKey(),
    ]);

    $content = app(CronService::class)->buildCrontab($server);

    expect($content)->toContain('# Managed by HelixDeploy — do not edit manually');
    expect($content)->toContain('# Last synced:');
    expect($content)->toContain("0 0 * * * php artisan schedule:run # helix:{$active->id}");
    expect($content)->not->toContain('queue:work');
});

it('describes common cron expressions in human-readable form', function (): void {
    $service = app(CronService::class);

    expect($service->describe('0 0 * * *'))->toBe('Every day at midnight');
});
