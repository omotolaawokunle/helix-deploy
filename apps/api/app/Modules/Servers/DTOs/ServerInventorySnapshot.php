<?php

declare(strict_types=1);

namespace App\Modules\Servers\DTOs;

final readonly class ServerInventorySnapshot
{
    /**
     * @param list<string> $serviceKeys
     * @param list<DiscoveredSiteSnapshot> $sites
     * @param list<DiscoveredDaemonSnapshot> $daemons
     * @param list<DiscoveredCronSnapshot> $crons
     */
    public function __construct(
        public array $serviceKeys,
        public array $sites,
        public array $daemons = [],
        public array $crons = [],
    ) {
    }
}
