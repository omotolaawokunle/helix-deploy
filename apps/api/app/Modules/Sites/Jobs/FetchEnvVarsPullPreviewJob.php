<?php

declare(strict_types=1);

namespace App\Modules\Sites\Jobs;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Actions\EnvVarActions\FetchEnvVarsPullPreviewAction;
use App\Modules\Sites\Events\EnvVarsPullPreviewReady;
use App\Modules\Sites\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class FetchEnvVarsPullPreviewJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public readonly string $siteId,
    ) {
        $this->onQueue('commands');
    }

    public function handle(FetchEnvVarsPullPreviewAction $fetchEnvVarsPullPreviewAction): void
    {
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

        $cacheKey = self::cacheKey($this->siteId);

        try {
            $org = Organization::query()->find((string) $site->organization_id);

            if ($org === null) {
                return;
            }

            $diff = $fetchEnvVarsPullPreviewAction->execute($site, $org);

            Cache::put($cacheKey, [
                'status' => 'ready',
                'diff' => $diff->toArray(),
            ], now()->addMinutes(5));

            event(new EnvVarsPullPreviewReady(
                serverId: (string) $server->getKey(),
                organizationId: (string) $site->organization_id,
                siteId: (string) $site->getKey(),
                status: 'ready',
                diff: $diff->toArray(),
            ));
        } catch (\Throwable) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'message' => 'Unable to fetch environment variable preview.',
            ], now()->addMinutes(1));

            event(new EnvVarsPullPreviewReady(
                serverId: (string) $server->getKey(),
                organizationId: (string) $site->organization_id,
                siteId: (string) $site->getKey(),
                status: 'failed',
                message: 'Unable to fetch environment variable preview.',
            ));
        }
    }

    public static function cacheKey(string $siteId): string
    {
        return 'env_vars_pull_preview:'.$siteId;
    }
}
