<?php

declare(strict_types=1);

namespace App\Modules\Servers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Servers\Actions\ListServerServicesAction;
use App\Modules\Servers\Jobs\RunServerServiceOperationJob;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Resources\ServerServiceResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class ServerServiceController extends Controller
{
    public function index(string $server, ListServerServicesAction $listServerServicesAction): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('view', $serverModel);

        return ServerServiceResource::collection(
            $listServerServicesAction->execute($serverModel),
        );
    }

    public function syncStatus(string $server): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('view', $serverModel);

        RunServerServiceOperationJob::dispatch(
            operation: 'sync-status',
            serverId: (string) $serverModel->getKey(),
        );

        return response()->json(['message' => 'Service status sync has been queued.'], 202);
    }

    public function start(string $server, string $service): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('manageServices', $serverModel);

        RunServerServiceOperationJob::dispatch(
            operation: 'start',
            serverId: (string) $serverModel->getKey(),
            serviceKey: $service,
        );

        return response()->json(['message' => 'Service start has been queued.'], 202);
    }

    public function stop(string $server, string $service): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('manageServices', $serverModel);

        RunServerServiceOperationJob::dispatch(
            operation: 'stop',
            serverId: (string) $serverModel->getKey(),
            serviceKey: $service,
        );

        return response()->json(['message' => 'Service stop has been queued.'], 202);
    }

    public function restart(string $server, string $service): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('manageServices', $serverModel);

        RunServerServiceOperationJob::dispatch(
            operation: 'restart',
            serverId: (string) $serverModel->getKey(),
            serviceKey: $service,
        );

        return response()->json(['message' => 'Service restart has been queued.'], 202);
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
