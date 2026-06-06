<?php

declare(strict_types=1);

namespace App\Modules\Sites\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Jobs\IssueSiteSslJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Resources\SiteResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SiteSslController extends Controller
{
    public function retry(string $site, Request $request): JsonResponse
    {
        $siteModel = $this->resolveSite($site);
        $this->authorize('retrySsl', $siteModel);

        if (! $siteModel->enable_ssl) {
            return response()->json([
                'message' => 'SSL is not enabled for this site.',
            ], 422);
        }

        if ($siteModel->ssl_status === SslStatus::ACTIVE) {
            return response()->json([
                'message' => 'SSL is already active for this site.',
            ], 422);
        }

        IssueSiteSslJob::dispatch((string) $siteModel->getKey());

        $siteModel->forceFill([
            'ssl_status' => SslStatus::PENDING->value,
            'ssl_error' => null,
        ])->save();

        return SiteResource::make($siteModel->refresh())
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
