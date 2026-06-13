<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Contracts\ServerServiceManagerInterface;
use App\Modules\Servers\Enums\ServiceRuntimeStatus;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Services\InstalledServiceRegistry;
use App\Packages\SSH\SSHManager;

class SyncServerServiceStatusesAction
{
    public function __construct(
        private readonly InstalledServiceRegistry $registry,
        private readonly ServerServiceManagerInterface $serviceManager,
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
    ) {
    }

    /**
     * @return array<string, ServiceRuntimeStatus>
     */
    public function execute(string $serverId): array
    {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($serverId)
            ->firstOrFail();

        $serviceKeys = $this->registry->installedControllableKeys($server);

        if ($serviceKeys === []) {
            return [];
        }

        $connection = $this->sshManager->connect($server, $this->credentialVault)->connect();

        try {
            $statuses = $this->serviceManager->syncStatuses($connection, $server, $serviceKeys);
        } finally {
            $connection->disconnect();
        }

        $this->persistStatuses($server, $statuses);

        return $statuses;
    }

    /**
     * @param array<string, ServiceRuntimeStatus> $statuses
     */
    public function persistStatuses(Server $server, array $statuses): void
    {
        if ($statuses === []) {
            return;
        }

        $installed = (array) $server->installed_services;
        $checkedAt = now()->toIso8601String();

        foreach ($statuses as $serviceKey => $status) {
            $existing = is_array($installed[$serviceKey] ?? null) ? $installed[$serviceKey] : [];
            $installed[$serviceKey] = array_merge($existing, [
                'installed' => true,
                'status' => $status->value,
                'statusCheckedAt' => $checkedAt,
            ]);
        }

        $server->forceFill(['installed_services' => $installed])->save();
    }
}
