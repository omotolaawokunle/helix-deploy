<?php

declare(strict_types=1);

namespace App\Modules\Servers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Services\ServerSslOverviewBuilder;
use App\Modules\Sites\Jobs\AdoptServerSslCertificatesJob;
use App\Modules\Sites\Jobs\RenewServerSslCertificatesJob;
use App\Modules\Sites\Jobs\SyncServerSslCertificatesJob;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

final class ServerSslController extends Controller
{
    public function index(string $server, ServerSslOverviewBuilder $overviewBuilder): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('viewSslCertificates', $serverModel);

        $syncQueued = false;

        if ($overviewBuilder->shouldSync($serverModel)) {
            SyncServerSslCertificatesJob::dispatch((string) $serverModel->getKey());
            $syncQueued = true;
        }

        $overview = $overviewBuilder->build($serverModel, $syncQueued);

        return response()->json(['data' => $overview], $syncQueued ? 202 : 200);
    }

    public function sync(string $server): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('syncSslCertificates', $serverModel);

        SyncServerSslCertificatesJob::dispatch((string) $serverModel->getKey());

        return response()->json([
            'message' => 'SSL certificate sync has been queued.',
        ], 202);
    }

    public function renew(string $server): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('renewSslCertificates', $serverModel);

        RenewServerSslCertificatesJob::dispatch((string) $serverModel->getKey());

        AuditLog::record(
            operation: 'server.ssl_certificates.renewal_queued',
            resource: $serverModel,
        );

        return response()->json([
            'message' => 'SSL certificate renewal has been queued for all active sites on this server.',
        ], 202);
    }

    public function adopt(string $server): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('adoptSslCertificates', $serverModel);

        AdoptServerSslCertificatesJob::dispatch((string) $serverModel->getKey());

        return response()->json([
            'message' => 'Adopting existing SSL certificates has been queued.',
        ], 202);
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
