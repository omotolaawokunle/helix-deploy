<?php

declare(strict_types=1);

use App\Modules\Monitoring\Services\RemoteLogReader;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHResult;

it('reads tail output as non-empty lines', function (): void {
    $ssh = (new FakeSSHConnection())
        ->connect()
        ->addResponse('tail -n 50*', new SSHResult('tail', 0, "line one\nline two\n\n", '', 0.01));

    $lines = (new RemoteLogReader())->tail($ssh, '/var/log/nginx/access.log', 50);

    expect($lines)->toBe(['line one', 'line two']);
});

it('caps requested line count at max lines', function (): void {
    $ssh = (new FakeSSHConnection())
        ->connect()
        ->addResponse('tail -n 500*', new SSHResult('tail', 0, 'ok', '', 0.01));

    (new RemoteLogReader())->tail($ssh, '/var/log/nginx/error.log', 900);

    expect($ssh->getExecutedCommands()[0])->toContain('tail -n 500');
});

it('enforces minimum line count', function (): void {
    $ssh = (new FakeSSHConnection())
        ->connect()
        ->addResponse('tail -n 10*', new SSHResult('tail', 0, 'ok', '', 0.01));

    (new RemoteLogReader())->tail($ssh, '/var/log/nginx/error.log', 3);

    expect($ssh->getExecutedCommands()[0])->toContain('tail -n 10');
});

it('tails the newest laravel log file including daily rotated logs', function (): void {
    $ssh = (new FakeSSHConnection())
        ->connect()
        ->addResponse('*laravel*.log*', new SSHResult('tail', 0, "[2026-06-13] local.ERROR: daily\n", '', 0.01));

    $lines = (new RemoteLogReader())->tailLatestMatching(
        $ssh,
        '/var/www/app.example.test/storage/logs',
        'laravel*.log',
        50,
    );

    expect($lines)->toBe(['[2026-06-13] local.ERROR: daily'])
        ->and($ssh->getExecutedCommands()[0])->toContain('laravel*.log')
        ->and($ssh->getExecutedCommands()[0])->toContain('laravel.log');
});

it('rejects unsupported glob patterns', function (): void {
    $ssh = (new FakeSSHConnection())->connect();

    expect(fn () => (new RemoteLogReader())->tailLatestMatching($ssh, '/tmp/logs', '*.log', 50))
        ->toThrow(InvalidArgumentException::class);
});
