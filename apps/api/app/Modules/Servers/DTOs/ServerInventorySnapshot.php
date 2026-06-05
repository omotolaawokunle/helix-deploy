<?php

declare(strict_types=1);

namespace App\Modules\Servers\DTOs;

final readonly class ServerInventorySnapshot
{
    /**
     * @param list<string> $serviceKeys
     * @param list<DiscoveredSiteSnapshot> $sites
     */
    public function __construct(
        public array $serviceKeys,
        public array $sites,
    ) {
    }
}
