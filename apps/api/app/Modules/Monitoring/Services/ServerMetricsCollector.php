<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Services;

use App\Modules\Monitoring\Contracts\ServerMetricsCollectorInterface;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use Throwable;

class ServerMetricsCollector implements ServerMetricsCollectorInterface
{
    private const CPU_COMMAND = "awk '/^cpu /{u=\$2+\$4;t=\$2+\$3+\$4+\$5;if(t>0)printf \"%.2f\",100*u/t}' /proc/stat";

    private const MEMORY_COMMAND = "free -m | awk 'NR==2{printf \"%.2f %d\", (\$3/\$2)*100, \$2}'";

    private const DISK_COMMAND = "df -BG / | awk 'NR==2{gsub(/G/,\"\",\$2);gsub(/G/,\"\",\$3);gsub(/%/,\"\",\$5);printf \"%s %s\", \$5, \$2}'";

    /**
     * @return array<string, mixed>|null
     */
    public function collect(Server $server, SSHConnectionInterface $connection): ?array
    {
        try {
            $cpu = $this->parseFloat($connection->run(self::CPU_COMMAND, timeout: 10)->stdout);
            [$memoryPercent, $memoryTotalMb] = $this->parseMemory(
                $connection->run(self::MEMORY_COMMAND, timeout: 10)->stdout,
            );
            [$diskPercent, $diskTotalGb] = $this->parseDisk(
                $connection->run(self::DISK_COMMAND, timeout: 10)->stdout,
            );

            return [
                'cpuPercent' => $cpu,
                'memoryUsedPercent' => $memoryPercent,
                'memoryTotalMb' => $memoryTotalMb,
                'diskUsedPercent' => $diskPercent,
                'diskTotalGb' => $diskTotalGb,
                'lastCheckedAt' => now()->toIso8601String(),
                'fingerprintVerified' => $server->health_status['fingerprintVerified'] ?? true,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function parseFloat(string $output): ?float
    {
        $value = trim($output);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @return array{0: float|null, 1: int|null}
     */
    private function parseMemory(string $output): array
    {
        $parts = preg_split('/\s+/', trim($output)) ?: [];

        if (count($parts) < 2) {
            return [null, null];
        }

        $percent = is_numeric($parts[0]) ? round((float) $parts[0], 2) : null;
        $totalMb = is_numeric($parts[1]) ? (int) $parts[1] : null;

        return [$percent, $totalMb];
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function parseDisk(string $output): array
    {
        $parts = preg_split('/\s+/', trim($output)) ?: [];

        if (count($parts) < 2) {
            return [null, null];
        }

        $percent = is_numeric($parts[0]) ? round((float) $parts[0], 2) : null;
        $totalGb = is_numeric($parts[1]) ? round((float) $parts[1], 2) : null;

        return [$percent, $totalGb];
    }
}
