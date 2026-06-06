<?php

declare(strict_types=1);

namespace App\Modules\Sites\Jobs;

use App\Modules\Sites\Actions\CreateSiteAction;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Events\SiteProvisioningFailed;
use App\Modules\Sites\Exceptions\NginxConfigInvalidException;
use App\Modules\Sites\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Throwable;

class CreateSiteJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly string $siteId,
        public readonly string $actorId,
    ) {
        $this->onQueue('provisioning');
    }

    public function handle(CreateSiteAction $createSiteAction): void
    {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->siteId)
            ->first();

        if ($site === null || $site->status !== SiteStatus::PROVISIONING) {
            return;
        }

        Auth::loginUsingId($this->actorId);

        $createSiteAction->provision($site);
    }

    public function failed(Throwable $exception): void
    {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->siteId)
            ->first();

        if ($site === null) {
            return;
        }

        if ($exception instanceof NginxConfigInvalidException) {
            return;
        }

        $site->forceFill(['status' => SiteStatus::FAILED->value])->save();

        event(new SiteProvisioningFailed(
            siteId: (string) $site->getKey(),
            serverId: (string) $site->server_id,
            organizationId: (string) $site->organization_id,
            domain: $site->domain,
            message: $exception->getMessage(),
            siteRemoved: false,
        ));
    }
}
