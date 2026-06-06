<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Pipelines\Actions\CreatePipelineAction;
use App\Modules\Pipelines\Actions\DeletePipelineAction;
use App\Modules\Pipelines\Actions\UpdatePipelineAction;
use App\Modules\Pipelines\DTOs\CreatePipelineDTO;
use App\Modules\Pipelines\DTOs\UpdatePipelineDTO;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Requests\StorePipelineRequest;
use App\Modules\Pipelines\Requests\UpdatePipelineRequest;
use App\Modules\Pipelines\Resources\PipelineResource;
use App\Modules\Pipelines\Services\PipelineTableFilterService;
use App\Modules\Teams\Contracts\TeamProjectVisibilityServiceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function index(
        Organization $org,
        Request $request,
        PipelineTableFilterService $tableFilterService,
        TeamProjectVisibilityServiceInterface $visibilityService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $this->authorize('viewAny', [Pipeline::class, $org]);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $query = Pipeline::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $org->getKey())
            ->withCount('sites')
            ->with('steps');

        $visibleProjectIds = $visibilityService->visibleProjectIds($user, $org);

        if ($visibleProjectIds !== null) {
            $query->where(function (Builder $builder) use ($visibleProjectIds): void {
                $builder
                    ->whereNull('project_id')
                    ->orWhereIn('project_id', $visibleProjectIds);
            });
        }

        $pipelines = $tableFilterService->paginate(
            query: $query,
            request: $request,
        );

        return PipelineResource::collection($pipelines);
    }

    public function store(
        Organization $org,
        StorePipelineRequest $request,
        CreatePipelineAction $action,
    ): JsonResponse {
        $this->authorize('create', [Pipeline::class, $org]);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $pipeline = $action->execute($org, $actor, CreatePipelineDTO::fromRequest($request));

        return PipelineResource::make($pipeline->loadCount('sites'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $pipeline): PipelineResource
    {
        $pipelineModel = $this->resolvePipeline($pipeline);
        $this->authorize('view', $pipelineModel);

        return PipelineResource::make(
            $pipelineModel->loadCount('sites')->load('steps'),
        );
    }

    public function update(
        string $pipeline,
        UpdatePipelineRequest $request,
        UpdatePipelineAction $action,
    ): PipelineResource {
        $pipelineModel = $this->resolvePipeline($pipeline);
        $this->authorize('update', $pipelineModel);

        $updated = $action->execute($pipelineModel, UpdatePipelineDTO::fromRequest($request));

        return PipelineResource::make($updated->loadCount('sites'));
    }

    public function destroy(string $pipeline, DeletePipelineAction $action): JsonResponse
    {
        $pipelineModel = $this->resolvePipeline($pipeline);
        $this->authorize('delete', $pipelineModel);

        $action->execute($pipelineModel);

        return response()->json(status: 204);
    }

    private function resolvePipeline(string $pipelineId): Pipeline
    {
        $pipeline = Pipeline::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($pipelineId)
            ->first();

        if ($pipeline === null) {
            throw (new ModelNotFoundException())->setModel(Pipeline::class, [$pipelineId]);
        }

        return $pipeline;
    }
}
