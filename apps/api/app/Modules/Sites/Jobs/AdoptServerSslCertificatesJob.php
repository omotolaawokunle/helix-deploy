<?php

declare(strict_types=1);

namespace App\Modules\Sites\Jobs;

use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Actions\AdoptServerSslCertificatesAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class AdoptServerSslCertificatesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(
        public readonly string $serverId,
    ) {
        $this->onQueue('provisioning');
    }

    public function handle(AdoptServerSslCertificatesAction $action): void
    {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->serverId)
            ->first();

        if ($server === null) {
            return;
        }

        $result = $action->execute($server);

        if ($result['adoptedCount'] > 0) {
            SyncServerSslCertificatesJob::dispatch((string) $server->getKey());
        }
    }
}
