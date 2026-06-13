<?php

declare(strict_types=1);

use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteLogReadMode;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteDeployPathResolver;
use App\Modules\Sites\Services\SiteLogPathResolver;

function siteLogPathResolver(): SiteLogPathResolver
{
    return new SiteLogPathResolver(new SiteDeployPathResolver());
}

it('resolves laravel application log directories from deploy paths for php sites', function (): void {
    $site = Site::query()->make([
        'domain' => 'app.example.test',
        'webroot' => '/var/www/app.example.test',
        'runtime' => Runtime::PHP,
    ]);

    $resolver = siteLogPathResolver();
    $target = $resolver->resolveTarget($site);

    expect($target?->mode)->toBe(SiteLogReadMode::LATEST_GLOB)
        ->and($target?->path)->toBe('/var/www/app.example.test/shared/logs')
        ->and($target?->globPattern)->toBe('laravel*.log')
        ->and($target?->resolvedPaths())->toBe([
            '/var/www/app.example.test/shared/logs',
            '/var/www/app.example.test/current/logs',
            '/var/www/app.example.test/logs',
            '/var/www/app.example.test/shared/storage/logs',
            '/var/www/app.example.test/current/storage/logs',
            '/var/www/app.example.test/storage/logs',
        ])
        ->and($resolver->supportsApplicationLogs($site))->toBeTrue();
});

it('resolves laravel log directories for capistrano webroot layout', function (): void {
    $site = Site::query()->make([
        'domain' => 'app.example.test',
        'webroot' => '/var/www/app.example.test/current/public',
        'runtime' => Runtime::PHP,
    ]);

    $target = siteLogPathResolver()->resolveTarget($site);

    expect($target?->resolvedPaths())->toContain('/var/www/app.example.test/shared/storage/logs')
        ->and($target?->resolvedPaths())->toContain('/var/www/app.example.test/current/storage/logs')
        ->and($target?->resolvedPaths())->toContain('/var/www/app.example.test/current/public/storage/logs');
});

it('resolves node application log paths from deploy candidates', function (): void {
    $site = Site::query()->make([
        'domain' => 'node.example.test',
        'webroot' => '/var/www/node.example.test/current/public',
        'runtime' => Runtime::NODEJS,
    ]);

    $resolver = siteLogPathResolver();
    $target = $resolver->resolveTarget($site);

    expect($target?->mode)->toBe(SiteLogReadMode::FILE)
        ->and($target?->path)->toBe('/var/www/node.example.test/shared/logs/error.log')
        ->and($target?->resolvedPaths())->toBe([
            '/var/www/node.example.test/shared/logs/error.log',
            '/var/www/node.example.test/current/logs/error.log',
            '/var/www/node.example.test/current/public/logs/error.log',
        ])
        ->and($resolver->supportsApplicationLogs($site))->toBeTrue();
});

it('returns null for unsupported application log runtimes', function (): void {
    $site = Site::query()->make([
        'domain' => 'static.example.test',
        'webroot' => '/var/www/static.example.test',
        'runtime' => Runtime::STATIC,
    ]);

    $resolver = siteLogPathResolver();

    expect($resolver->resolveTarget($site))->toBeNull()
        ->and($resolver->supportsApplicationLogs($site))->toBeFalse();
});
