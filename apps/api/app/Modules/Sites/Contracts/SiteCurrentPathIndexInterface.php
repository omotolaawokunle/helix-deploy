<?php

declare(strict_types=1);

namespace App\Modules\Sites\Contracts;

interface SiteCurrentPathIndexInterface
{
    /**
     * @return list<array{siteId: string, currentPath: string}>
     */
    public function forServer(string $serverId, string $organizationId): array;
}
