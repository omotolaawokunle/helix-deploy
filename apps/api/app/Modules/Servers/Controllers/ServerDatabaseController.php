<?php

declare(strict_types=1);

namespace App\Modules\Servers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Servers\Jobs\BrowseServerDatabaseJob;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Requests\BrowseServerDatabaseRowsRequest;
use App\Modules\Servers\Requests\BrowseServerDatabaseTablesRequest;
use App\Modules\Servers\Requests\BrowseServerDatabasesRequest;
use App\Modules\Servers\Resources\DatabaseBrowseResource;
use App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery;
use App\Packages\DatabaseBrowser\Enums\DatabaseBrowseKind;
use App\Packages\DatabaseBrowser\Enums\DatabaseEngine;
use App\Packages\DatabaseBrowser\Validation\SqlIdentifierValidator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ServerDatabaseController extends Controller
{
    public function databases(string $server, BrowseServerDatabasesRequest $request): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('browseDatabases', [$serverModel, $request->engine()]);

        return $this->respond(
            server: $serverModel,
            engine: $request->engine(),
            kind: DatabaseBrowseKind::DATABASES,
            database: null,
            table: null,
            rowQuery: null,
            refresh: $request->shouldRefresh(),
            auditOperation: 'database.databases_listed',
            auditMetadata: ['engine' => $request->engine()->value, 'mode' => 'server'],
        );
    }

    public function tables(
        string $server,
        string $database,
        BrowseServerDatabaseTablesRequest $request,
        SqlIdentifierValidator $identifierValidator,
    ): JsonResponse {
        $serverModel = $this->resolveServer($server);
        $this->authorize('browseDatabases', [$serverModel, $request->engine()]);
        $identifierValidator->assertDatabaseName($database);

        return $this->respond(
            server: $serverModel,
            engine: $request->engine(),
            kind: DatabaseBrowseKind::TABLES,
            database: $database,
            table: null,
            rowQuery: null,
            refresh: $request->shouldRefresh(),
            auditOperation: 'database.tables_listed',
            auditMetadata: [
                'engine' => $request->engine()->value,
                'mode' => 'server',
                'database' => $database,
            ],
        );
    }

    public function rows(
        string $server,
        string $database,
        string $table,
        BrowseServerDatabaseRowsRequest $request,
        SqlIdentifierValidator $identifierValidator,
    ): JsonResponse {
        $serverModel = $this->resolveServer($server);
        $this->authorize('browseDatabases', [$serverModel, $request->engine()]);
        $identifierValidator->assertDatabaseName($database);
        $identifierValidator->assertTableName($table);

        $rowQuery = $request->rowQuery();

        return $this->respond(
            server: $serverModel,
            engine: $request->engine(),
            kind: DatabaseBrowseKind::ROWS,
            database: $database,
            table: $table,
            rowQuery: $rowQuery,
            refresh: $request->shouldRefresh(),
            auditOperation: 'database.browsed',
            auditMetadata: [
                'engine' => $request->engine()->value,
                'mode' => 'server',
                'database' => $database,
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
        Server $server,
        DatabaseEngine $engine,
        DatabaseBrowseKind $kind,
        ?string $database,
        ?string $table,
        ?DatabaseRowQuery $rowQuery,
        bool $refresh,
        string $auditOperation,
        array $auditMetadata,
    ): JsonResponse {
        $org = $server->organization;
        abort_if($org === null, 404);

        $cacheKey = BrowseServerDatabaseJob::cacheKey($server->getKey(), $engine, $kind, $database, $table, $rowQuery);

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            BrowseServerDatabaseJob::dispatch(
                serverId: (string) $server->getKey(),
                organizationId: (string) $org->getKey(),
                engine: $engine,
                kind: $kind,
                database: $database,
                table: $table,
                rowQuery: $rowQuery,
            );

            AuditLog::record(
                operation: $auditOperation,
                resource: $server,
                metadata: $auditMetadata,
            );

            return (new DatabaseBrowseResource($this->resourcePayload(
                kind: $kind,
                engine: $engine,
                database: $database,
                table: $table,
                rowQuery: $rowQuery,
                status: 'loading',
            )))->response();
        }

        return (new DatabaseBrowseResource([
            ...$cached,
            ...$this->resourcePayload(
                kind: $kind,
                engine: $engine,
                database: $database ?? ($cached['database'] ?? null),
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
        DatabaseEngine $engine,
        ?string $database,
        ?string $table,
        ?DatabaseRowQuery $rowQuery,
        string $status,
    ): array {
        $limit = $rowQuery?->limit ?? 50;

        return [
            'status' => $status,
            'kind' => $kind->value,
            'engine' => $engine->value,
            'database' => $database,
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

    private function resolveServer(string $serverId): Server
    {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($serverId)
            ->first();

        if ($server === null) {
            throw (new ModelNotFoundException())->setModel(Server::class, [$serverId]);
        }

        return $server;
    }
}
