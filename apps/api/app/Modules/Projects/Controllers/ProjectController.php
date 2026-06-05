<?php

declare(strict_types=1);

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Actions\CreateProjectAction;
use App\Modules\Projects\Actions\DeleteProjectAction;
use App\Modules\Projects\Actions\UpdateProjectAction;
use App\Modules\Projects\DTOs\CreateProjectDTO;
use App\Modules\Projects\DTOs\UpdateProjectDTO;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Requests\StoreProjectRequest;
use App\Modules\Projects\Requests\UpdateProjectRequest;
use App\Modules\Projects\Resources\ProjectResource;
use App\Modules\Projects\Services\ProjectTableFilterService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(
        Organization $org,
        Request $request,
        ProjectTableFilterService $tableFilterService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $this->authorize('viewAny', [Project::class, $org]);

        $projects = $tableFilterService->paginate(
            query: Project::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $org->getKey())
                ->withCount(['environments', 'servers', 'sites']),
            request: $request,
        );

        return ProjectResource::collection($projects);
    }

    public function store(
        Organization $org,
        StoreProjectRequest $request,
        CreateProjectAction $action,
    ): JsonResponse {
        $this->authorize('create', [Project::class, $org]);

        $project = $action->execute($org, CreateProjectDTO::fromRequest($request));

        return ProjectResource::make($project)->response()->setStatusCode(201);
    }

    public function show(string $project): ProjectResource
    {
        $projectModel = $this->resolveProject($project);
        $this->authorize('view', $projectModel);

        $projectModel->loadCount(['environments', 'servers', 'sites']);

        return ProjectResource::make($projectModel);
    }

    public function update(
        string $project,
        UpdateProjectRequest $request,
        UpdateProjectAction $action,
    ): ProjectResource {
        $projectModel = $this->resolveProject($project);
        $this->authorize('update', $projectModel);

        $updated = $action->execute($projectModel, UpdateProjectDTO::fromRequest($request));

        return ProjectResource::make($updated->loadCount(['environments', 'servers', 'sites']));
    }

    public function destroy(string $project, DeleteProjectAction $action): JsonResponse
    {
        $projectModel = $this->resolveProject($project);
        $this->authorize('delete', $projectModel);

        $action->execute($projectModel);

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
}
