<?php

declare(strict_types=1);

use App\Modules\Servers\Services\ServerInventoryIntrospector;

it('parses discovered services and nginx sites from inventory output', function (): void {
    $introspector = new ServerInventoryIntrospector();

    $output = <<<'OUTPUT'
__HELIX_INVENTORY__
SVC:nginx
SVC:php
SVC:node
SVC:redis-cli
SITE:app.example.com|/var/www/app.example.com/public|php
SITE:api.example.com|/var/www/api|nodejs
SITE:default|/var/www/html|static
SITE:_|/var/www|static
__HELIX_END__
OUTPUT;

    $snapshot = $introspector->parseOutput($output);

    expect($snapshot->serviceKeys)->toBe(['nginx', 'php', 'nodejs', 'redis'])
        ->and($snapshot->sites)->toHaveCount(2)
        ->and($snapshot->sites[0]->domain)->toBe('app.example.com')
        ->and($snapshot->sites[0]->webroot)->toBe('/var/www/app.example.com/public')
        ->and($snapshot->sites[0]->runtime)->toBe('php')
        ->and($snapshot->sites[1]->domain)->toBe('api.example.com')
        ->and($snapshot->sites[1]->runtime)->toBe('nodejs')
        ->and($snapshot->daemons)->toBe([])
        ->and($snapshot->crons)->toBe([]);
});

it('parses supervisor programs and crontab lines from inventory output', function (): void {
    $encode = static fn (string $value): string => base64_encode($value);
    $introspector = new ServerInventoryIntrospector();
    $daemon = implode('|', [
        $encode('laravel_horizon'),
        $encode('/usr/bin/php /var/www/live/current/artisan horizon --no-interaction'),
        $encode('/var/www/live/current'),
        $encode('deploy'),
        $encode('1'),
        $encode('/etc/supervisor/conf.d/horizon.conf'),
    ]);
    $reverb = implode('|', [
        $encode('reverb'),
        $encode('/usr/bin/php /var/www/live/current/artisan reverb:start --no-interaction'),
        $encode('/var/www/live/current'),
        $encode('deploy'),
        $encode('1'),
        $encode('/etc/supervisor/conf.d/reverb.conf'),
    ]);
    $status = $encode('laravel_horizon:laravel_horizon_00 RUNNING pid 1, uptime 0:01:00');
    $cron = $encode('root').'|'.$encode('* * * * * cd /var/www/live/current && php artisan schedule:run >> /dev/null 2>&1');
    $helixLine = $encode('* * * * * www-data cd /var/www/nolbz && php artisan schedule:run # helix:abc');
    $pathLine = $encode('PATH=/usr/bin');

    $output = <<<OUTPUT
__HELIX_INVENTORY__
SVC:nginx
DAEMON:{$daemon}
DAEMON:{$reverb}
DSTAT:{$status}
CRON:{$cron}
CROND:{$helixLine}
CROND:{$pathLine}
__HELIX_END__
OUTPUT;

    $snapshot = $introspector->parseOutput($output);

    expect($snapshot->daemons)->toHaveCount(2)
        ->and($snapshot->daemons[0]->name)->toBe('laravel_horizon')
        ->and($snapshot->daemons[0]->status)->toBe('running')
        ->and($snapshot->daemons[0]->directory)->toBe('/var/www/live/current')
        ->and($snapshot->daemons[1]->name)->toBe('reverb')
        ->and($snapshot->daemons[1]->status)->toBe('stopped')
        ->and($snapshot->crons)->toHaveCount(1)
        ->and($snapshot->crons[0]->user)->toBe('root')
        ->and($snapshot->crons[0]->command)->toContain('schedule:run');
});

it('returns empty snapshot when inventory markers are missing', function (): void {
    $introspector = new ServerInventoryIntrospector();

    $snapshot = $introspector->parseOutput("no markers here\n");

    expect($snapshot->serviceKeys)->toBe([])
        ->and($snapshot->sites)->toBe([]);
});
