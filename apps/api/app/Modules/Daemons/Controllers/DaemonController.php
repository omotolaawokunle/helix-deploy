<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Daemons\Jobs\RunDaemonOperationJob;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Daemons\Requests\StoreDaemonRequest;
use App\Modules\Daemons\Resources\DaemonResource;
use App\Modules\Servers\Models\Server;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DaemonController extends Controller
{
    public function index(string $server): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('viewAny', [SupervisorProcess::class, $serverModel]);

        $daemons = SupervisorProcess::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $serverModel->getKey())
            ->where('organization_id', (string) $serverModel->organization_id)
            ->orderBy('name')
            ->get();

        return DaemonResource::collection($daemons);
    }

    public function store(string $server, StoreDaemonRequest $request): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('create', [SupervisorProcess::class, $serverModel]);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        RunDaemonOperationJob::dispatch(
            operation: 'create',
            serverId: (string) $serverModel->getKey(),
            actorId: (string) $actor->getKey(),
            dto: $request->toDto(),
        );

        return response()->json([
            'message' => 'Daemon creation has been queued.',
        ], 202);
    }

    public function restart(string $server, string $daemon, Request $request): JsonResponse
    {
        $daemonModel = $this->resolveDaemon($server, $daemon);
        $this->authorize('manage', $daemonModel);

        RunDaemonOperationJob::dispatch(operation: 'restart', daemonId: (string) $daemonModel->getKey());

        return response()->json(['message' => 'Daemon restart has been queued.'], 202);
    }

    public function start(string $server, string $daemon, Request $request): JsonResponse
    {
        $daemonModel = $this->resolveDaemon($server, $daemon);
        $this->authorize('manage', $daemonModel);

        RunDaemonOperationJob::dispatch(operation: 'start', daemonId: (string) $daemonModel->getKey());

        return response()->json(['message' => 'Daemon start has been queued.'], 202);
    }

    public function stop(string $server, string $daemon, Request $request): JsonResponse
    {
        $daemonModel = $this->resolveDaemon($server, $daemon);
        $this->authorize('manage', $daemonModel);

        RunDaemonOperationJob::dispatch(operation: 'stop', daemonId: (string) $daemonModel->getKey());

        return response()->json(['message' => 'Daemon stop has been queued.'], 202);
    }

    public function destroy(string $server, string $daemon, Request $request): JsonResponse
    {
        $daemonModel = $this->resolveDaemon($server, $daemon);
        $this->authorize('delete', $daemonModel);

        RunDaemonOperationJob::dispatch(operation: 'delete', daemonId: (string) $daemonModel->getKey());

        return response()->json(['message' => 'Daemon deletion has been queued.'], 202);
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

    private function resolveDaemon(string $serverId, string $daemonId): SupervisorProcess
    {
        $server = $this->resolveServer($serverId);

        $daemon = SupervisorProcess::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($daemonId)
            ->where('server_id', (string) $server->getKey())
            ->where('organization_id', (string) $server->organization_id)
            ->first();

        if ($daemon === null) {
            throw (new ModelNotFoundException())->setModel(SupervisorProcess::class, [$daemonId]);
        }

        return $daemon;
    }
}
