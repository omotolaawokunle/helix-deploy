<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Contracts;

use App\Modules\Servers\DTOs\DiscoveredCronSnapshot;
use App\Modules\Servers\Models\Server;

interface DiscoveredCronImporterInterface
{
    /**
     * @param list<DiscoveredCronSnapshot> $jobs
     * @param list<array{siteId: string, currentPath: string}> $sitePaths
     * @return array{created: int, updated: int}
     */
    public function import(Server $server, array $jobs, array $sitePaths): array;
}
