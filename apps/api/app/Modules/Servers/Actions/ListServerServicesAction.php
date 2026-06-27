<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Modules\Servers\DTOs\InstalledServiceDTO;
use App\Modules\Servers\Enums\ServiceRuntimeStatus;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Services\InstalledServiceRegistry;

class ListServerServicesAction
{
    public function __construct(
        private readonly InstalledServiceRegistry $registry,
    ) {
    }

    /**
     * @return list<InstalledServiceDTO>
     */
    public function execute(Server $server): array
    {
        $installed = (array) $server->installed_services;
        $services = [];

        foreach ($installed as $key => $metadata) {
            if (! is_array($metadata) || ($metadata['installed'] ?? false) !== true) {
                continue;
            }

            $serviceKey = (string) $key;
            $controllable = $this->registry->isControllable($serviceKey);
            $statusValue = $metadata['status'] ?? null;
            $status = is_string($statusValue)
                ? ServiceRuntimeStatus::tryFrom($statusValue) ?? ServiceRuntimeStatus::UNKNOWN
                : ServiceRuntimeStatus::UNKNOWN;

            $services[] = new InstalledServiceDTO(
                key: $serviceKey,
                label: $this->registry->labelFor($serviceKey),
                installed: true,
                status: $status,
                statusCheckedAt: is_string($metadata['statusCheckedAt'] ?? null) ? $metadata['statusCheckedAt'] : null,
                controllable: $controllable,
                version: is_string($metadata['version'] ?? null) ? $metadata['version'] : null,
            );
        }

        usort(
            $services,
            static fn (InstalledServiceDTO $left, InstalledServiceDTO $right): int => strcmp($left->key, $right->key),
        );

        return $services;
    }
}
