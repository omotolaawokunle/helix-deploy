<?php

declare(strict_types=1);

namespace App\Modules\Sites\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Sites\Actions\UpdateNginxConfigAction;
use App\Modules\Sites\Exceptions\NginxConfigInvalidException;
use App\Modules\Sites\Jobs\FetchSiteNginxConfigJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Requests\FetchNginxConfigRequest;
use App\Modules\Sites\Requests\UpdateNginxConfigRequest;
use App\Modules\Sites\Resources\NginxConfigResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class NginxConfigController extends Controller
{
    public function show(string $site, FetchNginxConfigRequest $request): NginxConfigResource
    {
        $siteModel = $this->resolveSite($site);
        $this->authorize('view', $siteModel);

        $cacheKey = FetchSiteNginxConfigJob::cacheKey((string) $siteModel->getKey());

        if ($request->shouldRefresh()) {
            Cache::forget($cacheKey);
        }

        /** @var array{status: string, config: string, message?: string}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            Cache::put($cacheKey, [
                'status' => 'loading',
                'config' => '',
            ], now()->addMinutes(2));

            FetchSiteNginxConfigJob::dispatch((string) $siteModel->getKey());

            AuditLog::record(
                operation: 'site.nginx_config.viewed',
                resource: $siteModel,
                afterState: ['domain' => $siteModel->domain],
            );

            return NginxConfigResource::make([
                'siteId' => (string) $siteModel->getKey(),
                'domain' => $siteModel->domain,
                'status' => 'loading',
                'config' => '',
            ]);
        }

        return NginxConfigResource::make([
            'siteId' => (string) $siteModel->getKey(),
            'domain' => $siteModel->domain,
            'status' => $cached['status'],
            'config' => $cached['config'] ?? '',
            'message' => $cached['message'] ?? null,
        ]);
    }

    public function update(
        string $site,
        UpdateNginxConfigRequest $request,
        UpdateNginxConfigAction $updateNginxConfigAction,
    ): NginxConfigResource|JsonResponse {
        $siteModel = $this->resolveSite($site);
        $this->authorize('updateNginxConfig', $siteModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        try {
            $siteModel = $updateNginxConfigAction->execute($siteModel, $actor, $request->config());
        } catch (NginxConfigInvalidException $exception) {
            return response()->json([
                'message' => 'Nginx configuration test failed.',
                'error' => $exception->nginxTestOutput,
            ], 422);
        }

        return NginxConfigResource::make([
            'siteId' => (string) $siteModel->getKey(),
            'domain' => $siteModel->domain,
            'status' => 'ready',
            'config' => $request->config(),
            'updatedAt' => $siteModel->updated_at?->toIso8601String(),
        ]);
    }

    private function resolveSite(string $siteId): Site
    {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($siteId)
            ->first();

        if ($site === null) {
            throw (new ModelNotFoundException)->setModel(Site::class, [$siteId]);
        }

        return $site;
    }
}
