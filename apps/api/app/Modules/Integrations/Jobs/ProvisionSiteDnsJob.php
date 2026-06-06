<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Jobs;

use App\Modules\Integrations\Contracts\SiteDnsProvisionerInterface;
use App\Modules\Integrations\Enums\DnsStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProvisionSiteDnsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public readonly string $siteId,
    ) {
        $this->onQueue('provisioning');
    }

    public function handle(SiteDnsProvisionerInterface $siteDnsProvisioner): void
    {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->siteId)
            ->first();

        if ($site === null || ! $site->auto_create_dns) {
            return;
        }

        $site->forceFill(['dns_status' => DnsStatus::PENDING->value])->save();

        $siteDnsProvisioner->provision($site);
    }
}
