<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\BuildRunners\Actions\DeleteBuildRunnerAction;
use App\Modules\BuildRunners\Actions\RegisterBuildRunnerAction;
use App\Modules\BuildRunners\Actions\TestBuildRunnerConnectionAction;
use App\Modules\BuildRunners\DTOs\RegisterBuildRunnerDTO;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Requests\RegisterBuildRunnerRequest;
use App\Modules\BuildRunners\Requests\UpdateBuildRunnerRequest;
use App\Modules\BuildRunners\Resources\BuildRunnerRegistrationResource;
use App\Modules\BuildRunners\Resources\BuildRunnerResource;
use App\Modules\BuildRunners\Services\BuildRunnerTableFilterService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildRunnerController extends Controller
{
    public function index(
        Organization $org,
        Request $request,
        BuildRunnerTableFilterService $tableFilterService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $this->authorize('viewAny', [BuildRunner::class, $org]);

        $runners = $tableFilterService->paginate(
            query: BuildRunner::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $org->getKey())
                ->with('project'),
            request: $request,
        );

        return BuildRunnerResource::collection($runners);
    }

    public function store(
        Organization $org,
        RegisterBuildRunnerRequest $request,
        RegisterBuildRunnerAction $registerBuildRunnerAction,
    ): BuildRunnerRegistrationResource {
        $this->authorize('create', [BuildRunner::class, $org]);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $result = $registerBuildRunnerAction->execute(
            org: $org,
            actor: $actor,
            dto: RegisterBuildRunnerDTO::fromRequest($request),
        );

        return BuildRunnerRegistrationResource::make($result);
    }

    public function show(string $buildRunner): BuildRunnerResource
    {
        $runner = $this->resolveRunner($buildRunner);
        $this->authorize('view', $runner);

        return BuildRunnerResource::make($runner->loadMissing(['project']));
    }

    public function update(string $buildRunner, UpdateBuildRunnerRequest $request): BuildRunnerResource
    {
        $runner = $this->resolveRunner($buildRunner);
        $this->authorize('update', $runner);

        $validated = $request->validated();
        $project = $this->resolveProject($validated['projectId'] ?? null, (string) $runner->organization_id);

        $beforeState = [
            'name' => $runner->name,
            'status' => $runner->status?->value,
            'maxConcurrentBuilds' => $runner->max_concurrent_builds,
            'supportedRuntimes' => $runner->supported_runtimes,
            'projectId' => $runner->project_id,
        ];

        $runner->forceFill([
            'name' => $validated['name'] ?? $runner->name,
            'max_concurrent_builds' => $validated['maxConcurrentBuilds'] ?? $runner->max_concurrent_builds,
            'cpu_cores' => array_key_exists('cpuCores', $validated) ? $validated['cpuCores'] : $runner->cpu_cores,
            'ram_gb' => array_key_exists('ramGb', $validated) ? $validated['ramGb'] : $runner->ram_gb,
            'supported_runtimes' => $validated['supportedRuntimes'] ?? $runner->supported_runtimes,
            'status' => $validated['status'] ?? $runner->status?->value,
            'project_id' => array_key_exists('projectId', $validated) ? $project?->getKey() : $runner->project_id,
        ])->save();

        AuditLog::record(
            operation: 'build_runner.updated',
            resource: $runner,
            beforeState: $beforeState,
            afterState: [
                'name' => $runner->name,
                'status' => $runner->status?->value,
                'maxConcurrentBuilds' => $runner->max_concurrent_builds,
                'supportedRuntimes' => $runner->supported_runtimes,
                'projectId' => $runner->project_id,
            ],
        );

        return BuildRunnerResource::make($runner->refresh()->loadMissing(['project']));
    }

    public function destroy(string $buildRunner, Request $request, DeleteBuildRunnerAction $action): JsonResponse
    {
        $runner = $this->resolveRunner($buildRunner);
        $this->authorize('delete', $runner);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $action->execute($runner, $actor);

        return response()->json(status: 204);
    }

    public function testConnection(
        string $buildRunner,
        Request $request,
        TestBuildRunnerConnectionAction $action,
    ): JsonResponse {
        $runner = $this->resolveRunner($buildRunner);
        $this->authorize('update', $runner);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $action->execute($runner, $actor);

        return response()->json(status: 202);
    }

    private function resolveRunner(string $runnerId): BuildRunner
    {
        $runner = BuildRunner::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($runnerId)
            ->first();

        if ($runner === null) {
            throw (new ModelNotFoundException())->setModel(BuildRunner::class, [$runnerId]);
        }

        return $runner;
    }

    private function resolveProject(?string $projectId, string $organizationId): ?Project
    {
        if ($projectId === null) {
            return null;
        }

        $project = Project::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($projectId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($project === null) {
            throw (new ModelNotFoundException())->setModel(Project::class, [$projectId]);
        }

        return $project;
    }
}
