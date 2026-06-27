<?php

declare(strict_types=1);

namespace App\Modules\Servers\Jobs;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Actions\SyncServerInventoryAction;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Jobs\AdoptServerSslCertificatesJob;
use App\Packages\SSH\SSHManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DiscoverServerInventoryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $serverId,
    ) {
        $this->onQueue('monitoring');
    }

    public function handle(
        SSHManager $sshManager,
        CredentialVault $vault,
        SyncServerInventoryAction $syncServerInventory,
    ): void {
        $server = $this->loadServer();

        if ($server === null) {
            return;
        }

        try {
            $connection = $sshManager->connectAndVerify($server, $vault);
            $syncServerInventory->execute($server, $connection);

            if ($server->isManaged()) {
                AdoptServerSslCertificatesJob::dispatch((string) $server->getKey());
            }
        } finally {
            if (isset($connection)) {
                $connection->disconnect();
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }

    private function loadServer(): ?Server
    {
        return Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->with('credential')
            ->where(fn (Builder $query): Builder => $query->whereKey($this->serverId))
            ->first();
    }
}
