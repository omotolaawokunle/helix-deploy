<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Servers\Contracts\ServerServiceManagerInterface;
use App\Modules\Servers\Enums\ServiceRuntimeStatus;
use App\Modules\Servers\Exceptions\UncontrollableServiceException;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

class ServerServiceManager implements ServerServiceManagerInterface
{
    public function __construct(
        private readonly InstalledServiceRegistry $registry,
    ) {
    }

    public function getStatus(SSHConnectionInterface $connection, Server $server, string $serviceKey): ServiceRuntimeStatus
    {
        $unit = $this->resolveUnit($server, $serviceKey);

        return $this->readStatus($connection, $unit);
    }

    public function start(SSHConnectionInterface $connection, Server $server, string $serviceKey): ServiceRuntimeStatus
    {
        $unit = $this->resolveUnit($server, $serviceKey);
        $before = $this->readStatus($connection, $unit);

        $connection->run('sudo systemctl start '.escapeshellarg($unit))->throw();

        $after = $this->readStatus($connection, $unit);

        AuditLog::record(
            operation: 'server.service.started',
            resource: $server,
            beforeState: [
                'service' => $serviceKey,
                'unit' => $unit,
                'status' => $before->value,
            ],
            afterState: [
                'service' => $serviceKey,
                'unit' => $unit,
                'status' => $after->value,
            ],
        );

        return $after;
    }

    public function stop(SSHConnectionInterface $connection, Server $server, string $serviceKey): ServiceRuntimeStatus
    {
        $unit = $this->resolveUnit($server, $serviceKey);
        $before = $this->readStatus($connection, $unit);

        $connection->run('sudo systemctl stop '.escapeshellarg($unit))->throw();

        $after = $this->readStatus($connection, $unit);

        AuditLog::record(
            operation: 'server.service.stopped',
            resource: $server,
            beforeState: [
                'service' => $serviceKey,
                'unit' => $unit,
                'status' => $before->value,
            ],
            afterState: [
                'service' => $serviceKey,
                'unit' => $unit,
                'status' => $after->value,
            ],
        );

        return $after;
    }

    public function restart(SSHConnectionInterface $connection, Server $server, string $serviceKey): ServiceRuntimeStatus
    {
        $unit = $this->resolveUnit($server, $serviceKey);
        $before = $this->readStatus($connection, $unit);

        $connection->run('sudo systemctl restart '.escapeshellarg($unit))->throw();

        $after = $this->readStatus($connection, $unit);

        AuditLog::record(
            operation: 'server.service.restarted',
            resource: $server,
            beforeState: [
                'service' => $serviceKey,
                'unit' => $unit,
                'status' => $before->value,
            ],
            afterState: [
                'service' => $serviceKey,
                'unit' => $unit,
                'status' => $after->value,
            ],
        );

        return $after;
    }

    public function syncStatuses(SSHConnectionInterface $connection, Server $server, array $serviceKeys): array
    {
        if ($serviceKeys === []) {
            return [];
        }

        $units = [];

        foreach ($serviceKeys as $serviceKey) {
            $units[$serviceKey] = $this->resolveUnit($server, $serviceKey);
        }

        $script = $this->buildStatusScript($units);
        $result = $connection->run($script);
        $parsed = $this->parseStatusScriptOutput($result->output());

        $statuses = [];

        foreach ($serviceKeys as $serviceKey) {
            $statuses[$serviceKey] = $parsed[$serviceKey] ?? ServiceRuntimeStatus::UNKNOWN;
        }

        return $statuses;
    }

    private function resolveUnit(Server $server, string $serviceKey): string
    {
        if (! $this->registry->isControllable($serviceKey)) {
            throw new UncontrollableServiceException("Service [{$serviceKey}] is not systemd-managed.");
        }

        return $this->registry->unitFor($server, $serviceKey);
    }

    private function readStatus(SSHConnectionInterface $connection, string $unit): ServiceRuntimeStatus
    {
        $result = $connection->run('systemctl is-active '.escapeshellarg($unit).' 2>/dev/null || true');

        return ServiceRuntimeStatus::fromSystemctlOutput($result->output());
    }

    /**
     * @param array<string, string> $units
     */
    private function buildStatusScript(array $units): string
    {
        $lines = ['export LC_ALL=C'];

        foreach ($units as $serviceKey => $unit) {
            $escapedUnit = escapeshellarg($unit);
            $escapedKey = escapeshellarg($serviceKey);
            $lines[] = "status=\$(systemctl is-active {$escapedUnit} 2>/dev/null || true)";
            $lines[] = "echo \"STATUS:{$serviceKey}|\$status\"";
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, ServiceRuntimeStatus>
     */
    private function parseStatusScriptOutput(string $output): array
    {
        $statuses = [];

        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
            $line = trim($line);


            if (! str_starts_with($line, 'STATUS:')) {
                continue;
            }

            $parts = explode('|', substr($line, 7), 2);

            if (count($parts) !== 2) {
                continue;
            }

            [$serviceKey, $statusOutput] = $parts;
            $statuses[$serviceKey] = ServiceRuntimeStatus::fromSystemctlOutput($statusOutput);
        }

        return $statuses;
    }
}
