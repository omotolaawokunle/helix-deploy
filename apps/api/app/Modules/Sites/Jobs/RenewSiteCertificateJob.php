<?php

declare(strict_types=1);

namespace App\Modules\Sites\Jobs;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Sites\Contracts\SiteSslProvisionerInterface;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RenewSiteCertificateJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $siteId,
    ) {
        $this->onQueue('provisioning');
    }

    public function handle(SiteSslProvisionerInterface $siteSslProvisioner): void
    {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->siteId)
            ->first();

        if ($site === null || ! $site->enable_ssl || $site->ssl_status !== SslStatus::ACTIVE) {
            return;
        }

        try {
            $siteSslProvisioner->renew($site);
        } catch (\Throwable $exception) {
            AuditLog::record(
                operation: 'site.ssl_certificate.renewal_failed',
                resource: $site,
                afterState: [
                    'domain' => $site->domain,
                    'message' => $exception->getMessage(),
                    'attempt' => $this->attempts(),
                ],
            );

            throw $exception;
        }
    }
}
