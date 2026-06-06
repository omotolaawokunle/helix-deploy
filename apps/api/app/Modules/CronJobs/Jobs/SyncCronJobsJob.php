<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Jobs;

use App\Modules\Credentials\CredentialVault;
use App\Modules\CronJobs\Services\CronService;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\SSHManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCronJobsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public readonly string $serverId,
    ) {
        $this->onQueue('commands');
    }

    public function handle(CronService $cronService, SSHManager $sshManager, CredentialVault $credentialVault): void
    {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->serverId)
            ->first();

        if ($server === null) {
            return;
        }

        $connection = $sshManager->connect($server, $credentialVault)->connect();

        try {
            $cronService->sync($server, $connection);
        } finally {
            $connection->disconnect();
        }
    }
}
