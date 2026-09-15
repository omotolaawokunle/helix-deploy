<?php

declare(strict_types=1);

use App\Packages\Execution\Support\ArtisanMigrateShellCommand;

it('ensure missing mysql and postgres databases then runs artisan migrate', function (): void {
    $command = ArtisanMigrateShellCommand::forReleasePath('/var/www/app/releases/abc');

    expect($command)->toContain("cd '/var/www/app/releases/abc'")
        ->and($command)->toContain('create database if not exists')
        ->and($command)->toContain('pg_database')
        ->and($command)->toContain('create database')
        ->and($command)->toContain('php8.3 -r ')
        ->and($command)->toContain('php8.3 artisan migrate --force --no-interaction');
});

it('uses the requested php version for migrate commands', function (): void {
    $command = ArtisanMigrateShellCommand::forReleasePath('/var/www/app/releases/abc', '8.4');

    expect($command)->toContain('php8.4 -r ')
        ->and($command)->toContain('php8.4 artisan migrate --force --no-interaction')
        ->and($command)->not->toContain('php8.3 artisan');
});
