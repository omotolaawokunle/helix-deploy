<?php

declare(strict_types=1);

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Projects\Actions\CreateEnvironmentAction;
use App\Modules\Projects\Actions\DeleteEnvironmentAction;
use App\Modules\Projects\Actions\UpdateEnvironmentAction;
use App\Modules\Projects\DTOs\CreateEnvironmentDTO;
use App\Modules\Projects\DTOs\UpdateEnvironmentDTO;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Requests\StoreEnvironmentRequest;
use App\Modules\Projects\Requests\UpdateEnvironmentRequest;
use App\Modules\Projects\Resources\EnvironmentResource;
use App\Modules\Projects\Services\EnvironmentTableFilterService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnvironmentController extends Controller
{
    public function index(
        string $project,
        Request $request,
        EnvironmentTableFilterService $tableFilterService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $projectModel = $this->resolveProject($project);
        $this->authorize('viewAny', [Environment::class, $projectModel]);

        $environments = $tableFilterService->paginate(
            query: Environment::query()
                ->where('project_id', (string) $projectModel->getKey())
                ->where('organization_id', (string) $projectModel->organization_id),
            request: $request,
        );

        return EnvironmentResource::collection($environments);
    }

    public function store(
        string $project,
        StoreEnvironmentRequest $request,
        CreateEnvironmentAction $action,
    ): JsonResponse {
        $projectModel = $this->resolveProject($project);
        $this->authorize('create', [Environment::class, $projectModel]);

        $environment = $action->execute($projectModel, CreateEnvironmentDTO::fromRequest($request));

        return EnvironmentResource::make($environment)->response()->setStatusCode(201);
    }

    public function show(string $environment): EnvironmentResource
    {
        $environmentModel = $this->resolveEnvironment($environment);
        $this->authorize('view', $environmentModel);

        return EnvironmentResource::make($environmentModel);
    }

    public function update(
        string $project,
        string $environment,
        UpdateEnvironmentRequest $request,
        UpdateEnvironmentAction $action,
    ): EnvironmentResource {
        $projectModel = $this->resolveProject($project);
        $environmentModel = $this->resolveEnvironment($environment, $projectModel);

        $this->authorize('update', $environmentModel);

        $updated = $action->execute($environmentModel, UpdateEnvironmentDTO::fromRequest($request));

        return EnvironmentResource::make($updated);
    }

    public function destroy(
        string $project,
        string $environment,
        DeleteEnvironmentAction $action,
    ): JsonResponse {
        $projectModel = $this->resolveProject($project);
        $environmentModel = $this->resolveEnvironment($environment, $projectModel);

        $this->authorize('delete', $environmentModel);

        $action->execute($environmentModel);

        return response()->json(status: 204);
    }

    private function resolveProject(string $projectId): Project
    {
        $project = Project::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($projectId)
            ->first();

        if ($project === null) {
            throw (new ModelNotFoundException())->setModel(Project::class, [$projectId]);
        }

        return $project;
    }

    private function resolveEnvironment(string $environmentId, ?Project $project = null): Environment
    {
        $query = Environment::query()->whereKey($environmentId);

        if ($project !== null) {
            $query
                ->where('project_id', (string) $project->getKey())
                ->where('organization_id', (string) $project->organization_id);
        }

        $environment = $query->first();

        if ($environment === null) {
            throw (new ModelNotFoundException())->setModel(Environment::class, [$environmentId]);
        }

        return $environment;
    }
}
