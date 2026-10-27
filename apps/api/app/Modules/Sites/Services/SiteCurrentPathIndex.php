<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Sites\Contracts\SiteCurrentPathIndexInterface;
use App\Modules\Sites\Models\Site;

final class SiteCurrentPathIndex implements SiteCurrentPathIndexInterface
{
    public function __construct(
        private readonly SiteDeployPathResolver $paths,
    ) {
    }

    public function forServer(string $serverId, string $organizationId): array
    {
        $sites = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', $serverId)
            ->where('organization_id', $organizationId)
            ->get();

        $indexed = [];

        foreach ($sites as $site) {
            $currentPath = rtrim($this->paths->currentPath($site), '/');

            if ($currentPath === '') {
                continue;
            }

            $indexed[] = [
                'siteId' => (string) $site->getKey(),
                'currentPath' => $currentPath,
            ];
        }

        usort(
            $indexed,
            static fn (array $left, array $right): int => strlen($right['currentPath']) <=> strlen($left['currentPath']),
        );

        return $indexed;
    }
}
