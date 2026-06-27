<?php

declare(strict_types=1);

namespace App\Modules\Sites\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Sites\Jobs\BrowseSiteDatabaseJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Requests\BrowseSiteDatabaseRowsRequest;
use App\Modules\Sites\Requests\BrowseSiteDatabaseTablesRequest;
use App\Modules\Sites\Resources\SiteDatabaseBrowseResource;
use App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery;
use App\Packages\DatabaseBrowser\Enums\DatabaseBrowseKind;
use App\Packages\DatabaseBrowser\Validation\SqlIdentifierValidator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SiteDatabaseController extends Controller
{
    public function tables(string $site, BrowseSiteDatabaseTablesRequest $request): JsonResponse
    {
        $siteModel = $this->resolveSite($site);
        $this->authorize('browseDatabase', $siteModel);

        return $this->respond(
            site: $siteModel,
            kind: DatabaseBrowseKind::TABLES,
            table: null,
            rowQuery: null,
            refresh: $request->shouldRefresh(),
            auditOperation: 'database.tables_listed',
            auditMetadata: ['mode' => 'site', 'site_id' => (string) $siteModel->getKey()],
        );
    }

    public function rows(
        string $site,
        string $table,
        BrowseSiteDatabaseRowsRequest $request,
        SqlIdentifierValidator $identifierValidator,
    ): JsonResponse {
        $siteModel = $this->resolveSite($site);
        $this->authorize('browseDatabase', $siteModel);
        $identifierValidator->assertTableName($table);

        $rowQuery = $request->rowQuery();

        return $this->respond(
            site: $siteModel,
            kind: DatabaseBrowseKind::ROWS,
            table: $table,
            rowQuery: $rowQuery,
            refresh: $request->shouldRefresh(),
            auditOperation: 'database.browsed',
            auditMetadata: [
                'mode' => 'site',
                'site_id' => (string) $siteModel->getKey(),
                'table' => $table,
                'page' => $rowQuery->page,
                'limit' => $rowQuery->limit,
                'filters' => array_map(
                    static fn (\App\Packages\DatabaseBrowser\DTOs\DatabaseRowFilter $filter): array => $filter->toArray(),
                    $rowQuery->filters,
                ),
            ],
        );
    }

    /**
     * @param array<string, mixed> $auditMetadata
     */
    private function respond(
        Site $site,
        DatabaseBrowseKind $kind,
        ?string $table,
        ?DatabaseRowQuery $rowQuery,
        bool $refresh,
        string $auditOperation,
        array $auditMetadata,
    ): JsonResponse {
        $org = $site->organization;
        abort_if($org === null, 404);

        $cacheKey = BrowseSiteDatabaseJob::cacheKey((string) $site->getKey(), $kind, $table, $rowQuery);

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            BrowseSiteDatabaseJob::dispatch(
                siteId: (string) $site->getKey(),
                organizationId: (string) $org->getKey(),
                kind: $kind,
                table: $table,
                rowQuery: $rowQuery,
            );

            AuditLog::record(
                operation: $auditOperation,
                resource: $site,
                metadata: $auditMetadata,
            );

            return (new SiteDatabaseBrowseResource($this->resourcePayload(
                kind: $kind,
                table: $table,
                rowQuery: $rowQuery,
                status: 'loading',
            )))->response();
        }

        return (new SiteDatabaseBrowseResource([
            ...$cached,
            ...$this->resourcePayload(
                kind: $kind,
                table: $table ?? ($cached['table'] ?? null),
                rowQuery: $rowQuery,
                status: (string) ($cached['status'] ?? 'ready'),
            ),
        ]))->response();
    }

    /**
     * @return array<string, mixed>
     */
    private function resourcePayload(
        DatabaseBrowseKind $kind,
        ?string $table,
        ?DatabaseRowQuery $rowQuery,
        string $status,
    ): array {
        $limit = $rowQuery?->limit ?? 50;

        return [
            'status' => $status,
            'kind' => $kind->value,
            'table' => $table,
            'limit' => $limit,
            'page' => $rowQuery?->page ?? 1,
            'offset' => $rowQuery !== null ? $rowQuery->offset() : 0,
            'filters' => $rowQuery !== null
                ? array_map(
                    static fn (\App\Packages\DatabaseBrowser\DTOs\DatabaseRowFilter $filter): array => $filter->toArray(),
                    $rowQuery->filters,
                )
                : [],
        ];
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
