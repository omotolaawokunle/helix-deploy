<?php

declare(strict_types=1);

namespace App\Modules\Sites\Jobs;

use App\Modules\Sites\Contracts\SiteSslProvisionerInterface;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class IssueSiteSslJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

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

        if ($site === null || ! $site->enable_ssl) {
            return;
        }

        $site->forceFill(['ssl_status' => SslStatus::PENDING->value])->save();

        $siteSslProvisioner->issue($site);
    }
}
