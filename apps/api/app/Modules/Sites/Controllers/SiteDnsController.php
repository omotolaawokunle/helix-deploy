<?php

declare(strict_types=1);

namespace App\Modules\Sites\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Enums\DnsStatus;
use App\Modules\Integrations\Events\SiteDnsSslStatusChanged;
use App\Modules\Integrations\Jobs\ProvisionSiteDnsJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Resources\SiteResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SiteDnsController extends Controller
{
    public function retry(string $site, Request $request): JsonResponse
    {
        $siteModel = $this->resolveSite($site);
        $this->authorize('retryDns', $siteModel);

        if (! $siteModel->auto_create_dns) {
            return response()->json([
                'message' => 'DNS auto-create is not enabled for this site.',
            ], 422);
        }

        if ($siteModel->dns_status === DnsStatus::ACTIVE) {
            return response()->json([
                'message' => 'DNS is already active for this site.',
            ], 422);
        }

        ProvisionSiteDnsJob::dispatch((string) $siteModel->getKey());

        $siteModel->forceFill([
            'dns_status' => DnsStatus::PENDING->value,
            'dns_error' => null,
        ])->save();

        event(new SiteDnsSslStatusChanged($siteModel->refresh()));

        return SiteResource::make($siteModel)
            ->response()
            ->setStatusCode(202);
    }

    private function resolveSite(string $siteId): Site
    {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($siteId)
            ->first();

        if ($site === null) {
            throw (new ModelNotFoundException())->setModel(Site::class, [$siteId]);
        }

        return $site;
    }
}
