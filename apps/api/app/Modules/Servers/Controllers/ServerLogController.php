<?php

declare(strict_types=1);

namespace App\Modules\Servers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Servers\Jobs\FetchServerLogsJob;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Requests\FetchServerLogsRequest;
use App\Modules\Servers\Resources\ServerLogResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ServerLogController extends Controller
{
    public function show(string $server, FetchServerLogsRequest $request): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('viewLogs', $serverModel);

        $logType = $request->logType();
        $lines = $request->lineCount();
        $cacheKey = FetchServerLogsJob::cacheKey((string) $serverModel->getKey(), $logType, $lines);

        if ($request->shouldRefresh()) {
            Cache::forget($cacheKey);
        }

        /** @var array{status: string, lines: list<string>, message?: string}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            FetchServerLogsJob::dispatch(
                serverId: (string) $serverModel->getKey(),
                logType: $logType,
                lines: $lines,
            );

            AuditLog::record(
                operation: 'server.logs.viewed',
                resource: $serverModel,
                metadata: [
                    'log_type' => $logType->value,
                    'lines' => $lines,
                ],
            );

            return (new ServerLogResource([
                'status' => 'loading',
                'lines' => [],
                'logType' => $logType->value,
                'linesRequested' => $lines,
            ]))->response();
        }

        return (new ServerLogResource([
            'status' => $cached['status'],
            'lines' => $cached['lines'] ?? [],
            'message' => $cached['message'] ?? null,
            'logType' => $logType->value,
            'linesRequested' => $lines,
        ]))->response();
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
