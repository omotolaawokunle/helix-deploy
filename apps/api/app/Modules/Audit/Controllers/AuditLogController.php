<?php

declare(strict_types=1);

namespace App\Modules\Audit\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Jobs\ExportAuditLogsJob;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Policies\AuditLogPolicy;
use App\Modules\Audit\Requests\ExportAuditLogsRequest;
use App\Modules\Audit\Resources\AuditLogResource;
use App\Modules\Audit\Services\AuditLogCsvExporter;
use App\Modules\Audit\Services\AuditLogQueryService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuditLogController extends Controller
{
    public function indexForOrganization(
        Organization $org,
        Request $request,
        AuditLogQueryService $queryService,
        AuditLogPolicy $auditLogPolicy,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $this->authorize('viewAny', [AuditLog::class, $org]);

        AuditLogResource::$includeSensitiveState = $auditLogPolicy->viewSensitiveState($actor, $org);

        return AuditLogResource::collection(
            $queryService->cursorPaginateForOrganization($org, $request),
        );
    }

    public function indexForServer(
        string $server,
        Request $request,
        AuditLogQueryService $queryService,
        AuditLogPolicy $auditLogPolicy,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $serverModel = $this->resolveServer($server);
        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $organization = $serverModel->organization;
        $this->authorize('viewAny', [AuditLog::class, $organization]);

        AuditLogResource::$includeSensitiveState = $auditLogPolicy->viewSensitiveState($actor, $organization);

        return AuditLogResource::collection(
            $queryService->cursorPaginateForServer($serverModel, $request),
        );
    }

    public function export(
        Organization $org,
        ExportAuditLogsRequest $request,
        AuditLogQueryService $queryService,
        AuditLogCsvExporter $csvExporter,
    ): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse {
        $this->authorize('export', [AuditLog::class, $org]);

        if ($queryService->shouldQueueExport($org, $request)) {
            $exportId = (string) Str::uuid();

            ExportAuditLogsJob::dispatch(
                organizationId: (string) $org->getKey(),
                exportId: $exportId,
                filters: $request->query(),
            );

            return response()->json([
                'status' => 'queued',
                'exportId' => $exportId,
                'message' => 'Audit log export has been queued.',
            ], 202);
        }

        return $csvExporter->stream($queryService->exportQuery($org, $request));
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
