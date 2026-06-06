<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Contracts;

use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

interface ServerMetricsCollectorInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function collect(Server $server, SSHConnectionInterface $connection): ?array;
}
