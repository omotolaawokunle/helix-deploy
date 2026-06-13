<?php

declare(strict_types=1);

namespace App\Modules\Servers\Jobs;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Monitoring\Contracts\RemoteLogReaderInterface;
use App\Modules\Servers\Enums\ServerLogType;
use App\Modules\Servers\Events\ServerLogsReady;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Services\ServerLogPathResolver;
use App\Packages\SSH\SSHManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class FetchServerLogsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $serverId,
        public readonly ServerLogType $logType,
        public readonly int $lines,
    ) {
        $this->onQueue('monitoring');
    }

    public function handle(
        SSHManager $sshManager,
        ServerLogPathResolver $pathResolver,
        RemoteLogReaderInterface $logReader,
        CredentialVault $credentialVault,
    ): void {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->serverId)
            ->first();

        if ($server === null) {
            return;
        }

        $cacheKey = self::cacheKey($this->serverId, $this->logType, $this->lines);
        $path = $pathResolver->resolve($this->logType);

        try {
            $connection = $sshManager->connect($server, $credentialVault)->connect();

            try {
                $lines = $logReader->tail($connection, $path, $this->lines);

                Cache::put($cacheKey, [
                    'status' => 'ready',
                    'lines' => $lines,
                ], now()->addMinutes(5));

                event(new ServerLogsReady(
                    serverId: (string) $server->getKey(),
                    organizationId: (string) $server->organization_id,
                    logType: $this->logType->value,
                    linesRequested: $this->lines,
                    status: 'ready',
                    lines: $lines,
                ));
            } finally {
                $connection->disconnect();
            }
        } catch (\Throwable) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'lines' => [],
                'message' => 'Unable to fetch server logs.',
            ], now()->addMinutes(1));

            event(new ServerLogsReady(
                serverId: (string) $server->getKey(),
                organizationId: (string) $server->organization_id,
                logType: $this->logType->value,
                linesRequested: $this->lines,
                status: 'failed',
                message: 'Unable to fetch server logs.',
            ));
        }
    }

    public static function cacheKey(string $serverId, ServerLogType $logType, int $lines): string
    {
        return 'server_logs:'.$serverId.':'.$logType->value.':'.$lines;
    }
}
