<?php

declare(strict_types=1);

namespace App\Modules\Sites\Contracts;

use App\Modules\Servers\DTOs\DiscoveredSiteSnapshot;
use App\Modules\Servers\Models\Server;

interface DiscoveredSiteImporterInterface
{
    /**
     * @param list<DiscoveredSiteSnapshot> $sites
     * @return array{created: int, updated: int}
     */
    public function import(Server $server, array $sites): array;
}
