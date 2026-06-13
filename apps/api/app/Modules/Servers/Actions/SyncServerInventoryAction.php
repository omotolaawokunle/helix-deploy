<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Servers\Contracts\ServerInventoryIntrospectorInterface;
use App\Modules\Servers\Contracts\ServerServiceManagerInterface;
use App\Modules\Servers\DTOs\ServerInventorySnapshot;
use App\Modules\Servers\Events\ServerInventoryDiscovered;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Contracts\DiscoveredSiteImporterInterface;
use App\Modules\Servers\Services\InstalledServiceRegistry;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

class SyncServerInventoryAction
{
    public function __construct(
        private readonly ServerInventoryIntrospectorInterface $introspector,
        private readonly DiscoveredSiteImporterInterface $discoveredSiteImporter,
        private readonly InstalledServiceRegistry $serviceRegistry,
        private readonly ServerServiceManagerInterface $serviceManager,
    ) {
    }

    /**
     * @return array{
     *     installedServices: array<string, array<string, mixed>>,
     *     sitesCreated: int,
     *     sitesUpdated: int,
     *     discoveredSiteCount: int
     * }
     */
    public function execute(Server $server, SSHConnectionInterface $connection): array
    {
        $snapshot = $this->introspector->inspect($connection);
        $installedServices = $this->buildInstalledServices($server, $snapshot);
        $installedServices = $this->attachServiceStatuses($server, $installedServices, $connection);
        $siteImport = $this->discoveredSiteImporter->import($server, $snapshot->sites);

        $beforeServices = (array) $server->installed_services;

        $server->forceFill([
            'installed_services' => $installedServices,
            'health_status' => array_merge((array) ($server->health_status ?? []), [
                'lastInventoryAt' => now()->toIso8601String(),
                'discoveredSiteCount' => count($snapshot->sites),
            ]),
        ])->save();

        AuditLog::record(
            operation: 'server.inventory_synced',
            resource: $server,
            beforeState: [
                'installedServices' => array_keys($beforeServices),
            ],
            afterState: [
                'installedServices' => array_keys($installedServices),
                'sitesCreated' => $siteImport['created'],
                'sitesUpdated' => $siteImport['updated'],
                'discoveredSiteCount' => count($snapshot->sites),
            ],
        );

        $result = [
            'installedServices' => $installedServices,
            'sitesCreated' => $siteImport['created'],
            'sitesUpdated' => $siteImport['updated'],
            'discoveredSiteCount' => count($snapshot->sites),
        ];

        event(new ServerInventoryDiscovered($server->refresh(), $result));

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildInstalledServices(Server $server, ServerInventorySnapshot $snapshot): array
    {
        $services = (array) $server->installed_services;
        $detectedAt = now()->toIso8601String();

        foreach ($snapshot->serviceKeys as $serviceKey) {
            $existing = $services[$serviceKey] ?? [];

            $services[$serviceKey] = array_merge($existing, [
                'installed' => true,
                'detected_at' => $detectedAt,
                'source' => 'introspection',
            ]);
        }

        return $services;
    }

    /**
     * @param array<string, array<string, mixed>> $installedServices
     * @return array<string, array<string, mixed>>
     */
    private function attachServiceStatuses(
        Server $server,
        array $installedServices,
        SSHConnectionInterface $connection,
    ): array {
        $server->forceFill(['installed_services' => $installedServices]);
        $serviceKeys = $this->serviceRegistry->installedControllableKeys($server);

        if ($serviceKeys === []) {
            return $installedServices;
        }

        $statuses = $this->serviceManager->syncStatuses($connection, $server, $serviceKeys);
        $checkedAt = now()->toIso8601String();

        foreach ($statuses as $serviceKey => $status) {
            $existing = is_array($installedServices[$serviceKey] ?? null) ? $installedServices[$serviceKey] : [];
            $installedServices[$serviceKey] = array_merge($existing, [
                'installed' => true,
                'status' => $status->value,
                'statusCheckedAt' => $checkedAt,
            ]);
        }

        return $installedServices;
    }
}
