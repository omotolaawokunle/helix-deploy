<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Jobs;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Daemons\Services\SupervisorService;
use App\Packages\SSH\SSHManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class FetchDaemonLogsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $daemonId,
    ) {
        $this->onQueue('commands');
    }

    public function handle(
        SSHManager $sshManager,
        SupervisorService $supervisorService,
        CredentialVault $credentialVault,
    ): void {
        $daemon = SupervisorProcess::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->daemonId)
            ->first();

        if ($daemon === null) {
            return;
        }

        $server = $daemon->server;
        if ($server === null) {
            return;
        }

        $cacheKey = self::cacheKey($this->daemonId);

        try {
            $connection = $sshManager->connect($server, $credentialVault)->connect();

            try {
                $output = $supervisorService->getLogs($daemon, $connection, 50);
                $lines = array_values(array_filter(explode("\n", $output), static fn (string $line): bool => $line !== ''));

                Cache::put($cacheKey, [
                    'status' => 'ready',
                    'lines' => $lines,
                ], now()->addMinutes(5));
            } finally {
                $connection->disconnect();
            }
        } catch (\Throwable) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'lines' => [],
                'message' => 'Unable to fetch daemon logs.',
            ], now()->addMinutes(1));
        }
    }

    public static function cacheKey(string $daemonId): string
    {
        return 'daemon_logs:'.$daemonId;
    }
}
