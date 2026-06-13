<?php

declare(strict_types=1);

namespace App\Modules\Sites\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Sites\Enums\SiteLogType;
use App\Modules\Sites\Jobs\FetchSiteLogsJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Requests\FetchSiteLogsRequest;
use App\Modules\Sites\Resources\SiteLogResource;
use App\Modules\Sites\Services\SiteLogPathResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SiteLogController extends Controller
{
    public function show(string $site, FetchSiteLogsRequest $request, SiteLogPathResolver $pathResolver): JsonResponse
    {
        $siteModel = $this->resolveSite($site);
        $this->authorize('viewLogs', $siteModel);

        $logType = $request->logType();
        $lines = $request->lineCount();

        if ($logType === SiteLogType::APPLICATION && ! $pathResolver->supportsApplicationLogs($siteModel)) {
            return response()->json([
                'message' => 'Application logs are not available for this site runtime.',
            ], 422);
        }

        $cacheKey = FetchSiteLogsJob::cacheKey((string) $siteModel->getKey(), $logType, $lines);

        if ($request->shouldRefresh()) {
            Cache::forget($cacheKey);
        }

        /** @var array{status: string, lines: list<string>, message?: string}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            FetchSiteLogsJob::dispatch(
                siteId: (string) $siteModel->getKey(),
                logType: $logType,
                lines: $lines,
            );

            AuditLog::record(
                operation: 'site.logs.viewed',
                resource: $siteModel,
                metadata: [
                    'log_type' => $logType->value,
                    'lines' => $lines,
                ],
            );

            return (new SiteLogResource([
                'status' => 'loading',
                'lines' => [],
                'logType' => $logType->value,
                'linesRequested' => $lines,
            ]))->response();
        }

        return (new SiteLogResource([
            'status' => $cached['status'],
            'lines' => $cached['lines'] ?? [],
            'message' => $cached['message'] ?? null,
            'logType' => $logType->value,
            'linesRequested' => $lines,
        ]))->response();
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
