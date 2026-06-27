<?php

declare(strict_types=1);

namespace App\Modules\Sites\Jobs;

use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RenewServerSslCertificatesJob implements ShouldQueue
{
    use Queueable;

    private const int STAGGER_SECONDS = 5;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public readonly string $serverId,
    ) {
        $this->onQueue('provisioning');
    }

    public function handle(): void
    {
        $index = 0;

        Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', $this->serverId)
            ->where('enable_ssl', true)
            ->where('ssl_status', SslStatus::ACTIVE->value)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (mixed $siteId) use (&$index): void {
                if (! is_string($siteId) || $siteId === '') {
                    return;
                }

                RenewSiteCertificateJob::dispatch($siteId)
                    ->delay(now()->addSeconds($index * self::STAGGER_SECONDS));

                $index++;
            });
    }
}
