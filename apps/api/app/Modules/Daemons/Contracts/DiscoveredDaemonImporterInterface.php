<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Contracts;

use App\Modules\Servers\DTOs\DiscoveredDaemonSnapshot;
use App\Modules\Servers\Models\Server;

interface DiscoveredDaemonImporterInterface
{
    /**
     * @param list<DiscoveredDaemonSnapshot> $daemons
     * @param list<array{siteId: string, currentPath: string}> $sitePaths
     * @return array{created: int, updated: int}
     */
    public function import(Server $server, array $daemons, array $sitePaths): array;
}
