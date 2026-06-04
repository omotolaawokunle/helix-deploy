<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CronJobs\Actions\CreateCronJobAction;
use App\Modules\CronJobs\Actions\DeleteCronJobAction;
use App\Modules\CronJobs\Actions\ToggleCronJobAction;
use App\Modules\CronJobs\Actions\UpdateCronJobAction;
use App\Modules\CronJobs\Jobs\SyncCronJobsJob;
use App\Modules\CronJobs\Models\CronJob;
use App\Modules\CronJobs\Requests\StoreCronJobRequest;
use App\Modules\CronJobs\Requests\UpdateCronJobRequest;
use App\Modules\CronJobs\Resources\CronJobResource;
use App\Modules\Servers\Models\Server;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CronJobController extends Controller
{
    public function index(string $server): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('viewAny', [CronJob::class, $serverModel]);

        $jobs = CronJob::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $serverModel->getKey())
            ->where('organization_id', (string) $serverModel->organization_id)
            ->orderBy('created_at')
            ->get();

        return CronJobResource::collection($jobs);
    }

    public function store(
        string $server,
        StoreCronJobRequest $request,
        CreateCronJobAction $createCronJobAction,
    ): JsonResponse {
        $serverModel = $this->resolveServer($server);
        $this->authorize('create', [CronJob::class, $serverModel]);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $validated = $request->validated();

        $cronJob = $createCronJobAction->execute(
            server: $serverModel,
            actor: $actor,
            expression: (string) $validated['expression'],
            command: (string) $validated['command'],
            user: (string) ($validated['user'] ?? 'www-data'),
            active: (bool) ($validated['active'] ?? true),
        );

        return CronJobResource::make($cronJob)
            ->response()
            ->setStatusCode(201)
            ->header('Content-Type', 'application/json');
    }

    public function update(
        string $server,
        string $cronJob,
        UpdateCronJobRequest $request,
        UpdateCronJobAction $updateCronJobAction,
    ): CronJobResource {
        $serverModel = $this->resolveServer($server);
        $cronJobModel = $this->resolveCronJob($cronJob, $serverModel);

        $this->authorize('update', $cronJobModel);

        $cronJobModel = $updateCronJobAction->execute($cronJobModel, $request->validated());

        return CronJobResource::make($cronJobModel);
    }

    public function destroy(
        string $server,
        string $cronJob,
        Request $request,
        DeleteCronJobAction $deleteCronJobAction,
    ): JsonResponse {
        $serverModel = $this->resolveServer($server);
        $cronJobModel = $this->resolveCronJob($cronJob, $serverModel);

        $this->authorize('delete', $cronJobModel);

        $deleteCronJobAction->execute($cronJobModel);

        return response()->json(status: 204);
    }

    public function toggle(
        string $server,
        string $cronJob,
        Request $request,
        ToggleCronJobAction $toggleCronJobAction,
    ): CronJobResource {
        $serverModel = $this->resolveServer($server);
        $cronJobModel = $this->resolveCronJob($cronJob, $serverModel);

        $this->authorize('toggle', $cronJobModel);

        $cronJobModel = $toggleCronJobAction->execute($cronJobModel);

        return CronJobResource::make($cronJobModel);
    }

    public function sync(string $server, Request $request): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('sync', [CronJob::class, $serverModel]);

        SyncCronJobsJob::dispatch((string) $serverModel->getKey());

        return response()->json([
            'message' => 'Cron job sync has been queued.',
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

    private function resolveCronJob(string $cronJobId, Server $server): CronJob
    {
        $cronJob = CronJob::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($cronJobId)
            ->where('server_id', (string) $server->getKey())
            ->where('organization_id', (string) $server->organization_id)
            ->first();

        if ($cronJob === null) {
            throw (new ModelNotFoundException())->setModel(CronJob::class, [$cronJobId]);
        }

        return $cronJob;
    }
}
