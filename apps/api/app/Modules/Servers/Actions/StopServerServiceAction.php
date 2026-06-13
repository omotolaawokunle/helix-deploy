<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Contracts\ServerServiceManagerInterface;
use App\Modules\Servers\Enums\ServiceRuntimeStatus;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Services\InstalledServiceRegistry;
use App\Packages\SSH\SSHManager;
use Illuminate\Validation\ValidationException;

class StopServerServiceAction
{
    public function __construct(
        private readonly InstalledServiceRegistry $registry,
        private readonly ServerServiceManagerInterface $serviceManager,
        private readonly SyncServerServiceStatusesAction $syncServerServiceStatusesAction,
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
    ) {
    }

    public function execute(string $serverId, string $serviceKey): ServiceRuntimeStatus
    {
        $server = $this->resolveServer($serverId);
        $this->assertServiceInstalled($server, $serviceKey);

        $connection = $this->sshManager->connect($server, $this->credentialVault)->connect();

        try {
            $status = $this->serviceManager->stop($connection, $server, $serviceKey);
        } finally {
            $connection->disconnect();
        }

        $this->syncServerServiceStatusesAction->persistStatuses($server->refresh(), [$serviceKey => $status]);

        return $status;
    }

    private function resolveServer(string $serverId): Server
    {
        return Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($serverId)
            ->firstOrFail();
    }

    private function assertServiceInstalled(Server $server, string $serviceKey): void
    {
        $installed = (array) $server->installed_services;
        $metadata = $installed[$serviceKey] ?? null;

        if (! is_array($metadata) || ($metadata['installed'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'service' => 'The selected service is not installed on this server.',
            ]);
        }

        if (! $this->registry->isControllable($serviceKey)) {
            throw ValidationException::withMessages([
                'service' => 'The selected service is not systemd-managed.',
            ]);
        }
    }
}
