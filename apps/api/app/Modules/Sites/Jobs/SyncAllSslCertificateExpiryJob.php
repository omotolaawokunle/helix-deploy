<?php

declare(strict_types=1);

namespace App\Modules\Sites\Jobs;

use App\Modules\Servers\Enums\ManagementMode;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SyncAllSslCertificateExpiryJob implements ShouldQueue
{
    use Queueable;

    private const int STAGGER_SECONDS = 10;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('provisioning');
    }

    public function handle(): void
    {
        $index = 0;

        Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('management_mode', ManagementMode::MANAGED->value)
            ->whereIn('id', Site::query()
                ->withoutGlobalScope('owned_by_organization')
                ->select('server_id')
                ->where('enable_ssl', true)
                ->where('ssl_status', SslStatus::ACTIVE->value)
                ->whereNotNull('server_id')
                ->distinct())
            ->orderBy('id')
            ->pluck('id')
            ->each(function (mixed $serverId) use (&$index): void {
                if (! is_string($serverId) || $serverId === '') {
                    return;
                }

                SyncServerSslCertificatesJob::dispatch($serverId)
                    ->delay(now()->addSeconds($index * self::STAGGER_SECONDS));

                $index++;
            });
    }
}
