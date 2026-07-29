<?php

declare(strict_types=1);

use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\LaravelWorkerType;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\LaravelWorkerPresetBuilder;
use App\Modules\Sites\Services\SiteDeployPathResolver;

it('builds horizon preset from capistrano style webroot', function (): void {
    $site = new Site([
        'domain' => 'app.example.com',
        'webroot' => '/var/www/app.example.com/current/public',
        'runtime' => Runtime::PHP,
    ]);

    $builder = new LaravelWorkerPresetBuilder(new SiteDeployPathResolver());
    $preset = $builder->build($site, LaravelWorkerType::HORIZON);

    expect($preset->daemonName)->toBe('app-example-com-horizon')
        ->and($preset->daemonCommand)->toBe('php artisan horizon')
        ->and($preset->daemonDirectory)->toBe('/var/www/app.example.com/current')
        ->and($preset->cronExpression)->toBe('* * * * *')
        ->and($preset->cronCommand)->toBe('cd /var/www/app.example.com/current && php artisan schedule:run >> /dev/null 2>&1');
});

it('builds queue worker preset from deploy base webroot', function (): void {
    $site = new Site([
        'domain' => 'worker.example.test',
        'webroot' => '/home/deploy/worker.example.test/current',
        'runtime' => Runtime::PHP,
    ]);

    $builder = new LaravelWorkerPresetBuilder(new SiteDeployPathResolver());
    $preset = $builder->build($site, LaravelWorkerType::QUEUE);

    expect($preset->daemonName)->toBe('worker-example-test-queue')
        ->and($preset->daemonCommand)->toContain('queue:work')
        ->and($preset->daemonDirectory)->toBe('/home/deploy/worker.example.test/current');
});

it('sanitizes domain into supervisor slug', function (): void {
    $site = new Site([
        'id' => '00000000-0000-0000-0000-000000000001',
        'domain' => 'My_App.Example.COM',
        'webroot' => '/var/www/current/public',
        'runtime' => Runtime::PHP,
        'deploy_mode' => DeployMode::GIT,
        'status' => SiteStatus::ACTIVE,
    ]);

    $builder = new LaravelWorkerPresetBuilder(new SiteDeployPathResolver());

    expect($builder->slugFromSite($site))->toBe('my-app-example-com');
});
