<?php

declare(strict_types=1);

use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteDeployPathResolver;

it('derives deploy base from helix capistrano webroot', function (): void {
    $site = new Site([
        'domain' => 'app.example.test',
        'webroot' => '/var/www/app.example.test/current/public',
    ]);

    $resolver = new SiteDeployPathResolver();

    expect($resolver->deployBase($site))->toBe('/var/www/app.example.test')
        ->and($resolver->defaultEnvPath($site))->toBe('/var/www/app.example.test/shared/.env');
});

it('derives deploy base when webroot directory name differs from domain', function (): void {
    $site = new Site([
        'domain' => 'api.example.test',
        'webroot' => '/var/www/api/public',
    ]);

    $resolver = new SiteDeployPathResolver();

    expect($resolver->deployBase($site))->toBe('/var/www/api')
        ->and($resolver->sharedDirectory($site))->toBe('/var/www/api/shared')
        ->and($resolver->currentPath($site))->toBe('/var/www/api/current')
        ->and($resolver->releasePath($site, 'release-1'))->toBe('/var/www/api/releases/release-1')
        ->and($resolver->defaultEnvPath($site))->toBe('/var/www/api/shared/.env')
        ->and($resolver->envFileCandidates($site))->toBe([
            '/var/www/api/shared/.env',
            '/var/www/api/.env',
            '/var/www/api/current/.env',
        ]);
});

it('uses webroot as deploy base when no known suffix is present', function (): void {
    $site = new Site([
        'domain' => 'default.example.test',
        'webroot' => '/var/www/html',
        'runtime' => Runtime::STATIC->value,
        'deploy_mode' => DeployMode::GIT->value,
        'status' => SiteStatus::DISCOVERED->value,
    ]);

    $resolver = new SiteDeployPathResolver();

    expect($resolver->deployBase($site))->toBe('/var/www/html');
});
