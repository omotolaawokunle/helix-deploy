<?php

declare(strict_types=1);

namespace App\Modules\Servers\Contracts;

use App\Modules\Servers\Enums\ServiceRuntimeStatus;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

interface ServerServiceManagerInterface
{
    public function getStatus(SSHConnectionInterface $connection, Server $server, string $serviceKey): ServiceRuntimeStatus;

    public function start(SSHConnectionInterface $connection, Server $server, string $serviceKey): ServiceRuntimeStatus;

    public function stop(SSHConnectionInterface $connection, Server $server, string $serviceKey): ServiceRuntimeStatus;

    public function restart(SSHConnectionInterface $connection, Server $server, string $serviceKey): ServiceRuntimeStatus;

    /**
     * @param list<string> $serviceKeys
     * @return array<string, ServiceRuntimeStatus>
     */
    public function syncStatuses(SSHConnectionInterface $connection, Server $server, array $serviceKeys): array;
}
