<?php

declare(strict_types=1);

namespace App\Modules\Servers\DTOs;

final readonly class DiscoveredSiteSnapshot
{
    public function __construct(
        public string $domain,
        public string $webroot,
        public string $runtime,
    ) {
    }
}
