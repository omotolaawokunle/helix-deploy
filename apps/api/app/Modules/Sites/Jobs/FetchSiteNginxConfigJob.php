<?php

declare(strict_types=1);

namespace App\Modules\Sites\Jobs;

use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteNginxProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class FetchSiteNginxConfigJob implements ShouldQueue
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

    public function handle(SiteNginxProvisioner $siteNginxProvisioner): void
    {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->siteId)
            ->first();

        if ($site === null) {
            return;
        }

        $cacheKey = self::cacheKey($this->siteId);
        $server = $site->server;

        if ($server === null) {
            $this->storeFailure($cacheKey, 'Site server could not be resolved.');

            return;
        }

        try {
            $config = $siteNginxProvisioner->readInstalledConfig($server, $site);

            Cache::put($cacheKey, [
                'status' => 'ready',
                'config' => $config,
            ], now()->addMinutes(2));
        } catch (\Throwable) {
            $this->storeFailure($cacheKey, 'Unable to read the nginx configuration from the server.');
        }
    }

    public static function cacheKey(string $siteId): string
    {
        return 'site_nginx_config:'.$siteId;
    }

    private function storeFailure(string $cacheKey, string $message): void
    {
        Cache::put($cacheKey, [
            'status' => 'failed',
            'config' => '',
            'message' => $message,
        ], now()->addMinute());
    }
}
