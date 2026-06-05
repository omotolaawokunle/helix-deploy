<?php

declare(strict_types=1);

use App\Modules\Monitoring\Services\ServerMetricsCollector;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHResult;

it('parses cpu memory and disk metrics from ssh output', function (): void {
    $ssh = (new FakeSSHConnection())
        ->connect()
        ->addResponse('*awk*', new SSHResult('awk', 0, '24.50', '', 0.01))
        ->addResponse('*free -m*', new SSHResult('free', 0, '62.50 8192', '', 0.01))
        ->addResponse('*df -BG*', new SSHResult('df', 0, '45 100', '', 0.01));

    $server = Server::query()->make([
        'health_status' => ['fingerprintVerified' => true],
    ]);

    $metrics = (new ServerMetricsCollector())->collect($server, $ssh);

    expect($metrics)->not->toBeNull()
        ->and($metrics['cpuPercent'])->toBe(24.5)
        ->and($metrics['memoryUsedPercent'])->toBe(62.5)
        ->and($metrics['memoryTotalMb'])->toBe(8192)
        ->and($metrics['diskUsedPercent'])->toBe(45.0)
        ->and($metrics['diskTotalGb'])->toBe(100.0)
        ->and($metrics['lastCheckedAt'])->not->toBeEmpty();
});
