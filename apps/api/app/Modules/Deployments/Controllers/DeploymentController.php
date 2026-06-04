<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Deployments\Actions\CancelDeploymentAction;
use App\Modules\Deployments\Actions\RollbackDeploymentAction;
use App\Modules\Deployments\Actions\TriggerDeploymentAction;
use App\Modules\Deployments\DTOs\TriggerDeploymentDTO;
use App\Modules\Deployments\Exceptions\ConcurrentDeploymentException;
use App\Modules\Deployments\Exceptions\ObserveModeServerException;
use App\Modules\Deployments\Exceptions\ProductionRollbackReasonRequiredException;
use App\Modules\Deployments\Exceptions\ReleaseNotFoundException;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Requests\RollbackDeploymentRequest;
use App\Modules\Deployments\Requests\StoreDeploymentRequest;
use App\Modules\Deployments\Resources\DeploymentListResource;
use App\Modules\Deployments\Resources\DeploymentResource;
use App\Modules\Deployments\Resources\DeploymentWithStepsResource;
use App\Modules\Deployments\Services\DeploymentQueryService;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DeploymentController extends Controller
{
    public function store(
        string $site,
        StoreDeploymentRequest $request,
        TriggerDeploymentAction $triggerDeploymentAction,
    ): JsonResponse {
        $siteModel = $this->resolveSite($site);
        $this->authorize('execute', $siteModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        try {
            $deployment = $triggerDeploymentAction->execute(
                site: $siteModel,
                actor: $actor,
                dto: new TriggerDeploymentDTO(branch: $request->branch()),
            );
        } catch (ConcurrentDeploymentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return DeploymentResource::make($deployment)
            ->additional([
                'channel' => 'deployment.'.$deployment->getKey(),
            ])
            ->response()
            ->setStatusCode(202);
    }

    public function indexForSite(
        string $site,
        Request $request,
        DeploymentQueryService $queryService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $siteModel = $this->resolveSite($site);
        $this->authorize('view', $siteModel);

        return DeploymentListResource::collection(
            $queryService->cursorPaginateForSite($siteModel, $request),
        );
    }

    public function show(string $deployment): DeploymentWithStepsResource
    {
        $deploymentModel = $this->resolveDeployment($deployment);
        $this->authorize('view', $deploymentModel);
        $deploymentModel->load(['steps', 'site.environment', 'triggeredBy']);

        return DeploymentWithStepsResource::make($deploymentModel);
    }

    public function cancel(
        string $deployment,
        Request $request,
        CancelDeploymentAction $cancelDeploymentAction,
    ): DeploymentResource {
        $deploymentModel = $this->resolveDeployment($deployment);
        $this->authorize('cancel', $deploymentModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        try {
            $cancelled = $cancelDeploymentAction->execute($deploymentModel, $actor);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return DeploymentResource::make($cancelled->load('triggeredBy'));
    }

    public function rollback(
        string $deployment,
        RollbackDeploymentRequest $request,
        RollbackDeploymentAction $rollbackDeploymentAction,
    ): JsonResponse {
        $original = $this->resolveDeployment($deployment);
        $this->authorize('rollback', $original);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        try {
            $rollback = $rollbackDeploymentAction->execute(
                original: $original,
                actor: $actor,
                reason: $request->reason(),
            );
        } catch (ReleaseNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (ConcurrentDeploymentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (ObserveModeServerException|ProductionRollbackReasonRequiredException|InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return DeploymentResource::make($rollback)
            ->additional([
                'channel' => 'deployment.'.$rollback->getKey(),
            ])
            ->response()
            ->setStatusCode(202);
    }

    private function resolveSite(string $siteId): Site
    {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($siteId)
            ->first();

        if ($site === null) {
            throw (new ModelNotFoundException())->setModel(Site::class, [$siteId]);
        }

        return $site;
    }

    private function resolveDeployment(string $deploymentId): Deployment
    {
        $deployment = Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($deploymentId)
            ->first();

        if ($deployment === null) {
            throw (new ModelNotFoundException())->setModel(Deployment::class, [$deploymentId]);
        }

        return $deployment;
    }
}
