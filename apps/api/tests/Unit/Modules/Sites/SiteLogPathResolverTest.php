<?php

declare(strict_types=1);

use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteLogReadMode;
use App\Modules\Sites\Enums\SiteLogType;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteLogPathResolver;

it('resolves nginx log paths from site domain', function (): void {
    $site = Site::query()->make([
        'domain' => 'app.example.test',
        'webroot' => '/var/www/app.example.test',
        'runtime' => Runtime::PHP,
    ]);

    $resolver = new SiteLogPathResolver();

    $access = $resolver->resolveTarget($site, SiteLogType::NGINX_ACCESS);
    $error = $resolver->resolveTarget($site, SiteLogType::NGINX_ERROR);

    expect($access?->mode)->toBe(SiteLogReadMode::FILE)
        ->and($access?->path)->toBe('/var/log/nginx/app.example.test-access.log')
        ->and($error?->path)->toBe('/var/log/nginx/app.example.test-error.log');
});

it('resolves laravel application log directory with glob for php sites', function (): void {
    $site = Site::query()->make([
        'domain' => 'app.example.test',
        'webroot' => '/var/www/app.example.test',
        'runtime' => Runtime::PHP,
    ]);

    $resolver = new SiteLogPathResolver();
    $target = $resolver->resolveTarget($site, SiteLogType::APPLICATION);

    expect($target?->mode)->toBe(SiteLogReadMode::LATEST_GLOB)
        ->and($target?->path)->toBe('/var/www/app.example.test/storage/logs')
        ->and($target?->globPattern)->toBe('laravel*.log')
        ->and($resolver->supportsApplicationLogs($site))->toBeTrue();
});

it('resolves node application log path for nodejs sites', function (): void {
    $site = Site::query()->make([
        'domain' => 'node.example.test',
        'webroot' => '/var/www/node.example.test',
        'runtime' => Runtime::NODEJS,
    ]);

    $resolver = new SiteLogPathResolver();
    $target = $resolver->resolveTarget($site, SiteLogType::APPLICATION);

    expect($target?->mode)->toBe(SiteLogReadMode::FILE)
        ->and($target?->path)->toBe('/var/www/node.example.test/logs/error.log')
        ->and($resolver->supportsApplicationLogs($site))->toBeTrue();
});

it('returns null for unsupported application log runtimes', function (): void {
    $site = Site::query()->make([
        'domain' => 'static.example.test',
        'webroot' => '/var/www/static.example.test',
        'runtime' => Runtime::STATIC,
    ]);

    $resolver = new SiteLogPathResolver();

    expect($resolver->resolveTarget($site, SiteLogType::APPLICATION))->toBeNull()
        ->and($resolver->supportsApplicationLogs($site))->toBeFalse();
});
