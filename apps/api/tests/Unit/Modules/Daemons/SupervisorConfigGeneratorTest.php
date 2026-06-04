<?php

declare(strict_types=1);

use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Daemons\Services\SupervisorConfigGenerator;

it('generates valid supervisor ini with expected fields', function (): void {
    $daemon = new SupervisorProcess([
        'name' => 'laravel-worker',
        'command' => 'php /var/www/example/current/artisan queue:work',
        'directory' => '/var/www/example/current',
        'user' => 'deploy',
        'processes' => 2,
    ]);

    $config = app(SupervisorConfigGenerator::class)->generate($daemon);

    expect($config)->toContain('[program:laravel-worker]');
    expect($config)->toContain('command=php /var/www/example/current/artisan queue:work');
    expect($config)->toContain('directory=/var/www/example/current');
    expect($config)->toContain('user=deploy');
    expect($config)->toContain('numprocs=2');
    expect($config)->toContain('autostart=true');
    expect($config)->toContain('autorestart=true');
    expect($config)->toContain('startretries=3');
    expect($config)->toContain('redirect_stderr=true');
    expect($config)->toContain('stdout_logfile=/var/log/supervisor/laravel-worker.log');
    expect($config)->toContain('stdout_logfile_maxbytes=5MB');
    expect($config)->toContain('stdout_logfile_backups=3');
});

it('defaults directory to /var/www when not set', function (): void {
    $daemon = new SupervisorProcess([
        'name' => 'horizon',
        'command' => 'php artisan horizon',
        'directory' => null,
        'user' => 'www-data',
        'processes' => 1,
    ]);

    $config = app(SupervisorConfigGenerator::class)->generate($daemon);

    expect($config)->toContain('directory=/var/www');
});
