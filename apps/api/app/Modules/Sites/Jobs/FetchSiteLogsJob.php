<?php

declare(strict_types=1);

namespace App\Modules\Sites\Jobs;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Monitoring\Contracts\RemoteLogReaderInterface;
use App\Modules\Sites\Enums\SiteLogReadMode;
use App\Modules\Sites\Enums\SiteLogType;
use App\Modules\Sites\Events\SiteLogsReady;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteLogPathResolver;
use App\Packages\SSH\SSHManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class FetchSiteLogsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $siteId,
        public readonly int $lines,
    ) {
        $this->onQueue('monitoring');
    }

    public function handle(
        SSHManager $sshManager,
        SiteLogPathResolver $pathResolver,
        RemoteLogReaderInterface $logReader,
        CredentialVault $credentialVault,
    ): void {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->siteId)
            ->first();

        if ($site === null) {
            return;
        }

        $server = $site->server;
        if ($server === null) {
            return;
        }

        $cacheKey = self::cacheKey($this->siteId, $this->lines);
        $target = $pathResolver->resolveTarget($site);

        if ($target === null) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'lines' => [],
                'message' => 'Application logs are not available for this site runtime.',
            ], now()->addMinutes(1));

            event(new SiteLogsReady(
                serverId: (string) $server->getKey(),
                organizationId: (string) $site->organization_id,
                siteId: (string) $site->getKey(),
                logType: SiteLogType::APPLICATION->value,
                linesRequested: $this->lines,
                status: 'failed',
                message: 'Application logs are not available for this site runtime.',
            ));

            return;
        }

        try {
            $connection = $sshManager->connect($server, $credentialVault)->connect();

            try {
                $lines = match ($target->mode) {
                    SiteLogReadMode::FILE => $logReader->tailFirstExisting($connection, $target->resolvedPaths(), $this->lines),
                    SiteLogReadMode::LATEST_GLOB => $logReader->tailLatestFromDirectories(
                        $connection,
                        $target->resolvedPaths(),
                        $target->globPattern,
                        $this->lines,
                    ),
                };
                Cache::put($cacheKey, [
                    'status' => 'ready',
                    'lines' => $lines,
                ], now()->addMinutes(5));
                \Illuminate\Support\Facades\Log::info('Fetch site logs job lines', ['lines' => $lines]);
                event(new SiteLogsReady(
                    serverId: (string) $server->getKey(),
                    organizationId: (string) $site->organization_id,
                    siteId: (string) $site->getKey(),
                    logType: SiteLogType::APPLICATION->value,
                    linesRequested: $this->lines,
                    status: 'ready',
                    lines: $lines,
                ));
            } finally {
                $connection->disconnect();
            }
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::info('Fetch site logs job error', ['error' => $th->getMessage(), 'trace' => $th->getTraceAsString()]);
            Cache::put($cacheKey, [
                'status' => 'failed',
                'lines' => [],
                'message' => 'Unable to fetch site logs.',
            ], now()->addMinutes(1));

            event(new SiteLogsReady(
                serverId: (string) $server->getKey(),
                organizationId: (string) $site->organization_id,
                siteId: (string) $site->getKey(),
                logType: SiteLogType::APPLICATION->value,
                linesRequested: $this->lines,
                status: 'failed',
                message: 'Unable to fetch site logs.',
            ));
        }
    }

    public static function cacheKey(string $siteId, int $lines): string
    {
        return 'site_logs:' . $siteId . ':' . SiteLogType::APPLICATION->value . ':' . $lines;
    }
}
