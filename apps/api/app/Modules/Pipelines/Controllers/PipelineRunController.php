<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pipelines\Actions\ApprovePipelineRunAction;
use App\Modules\Pipelines\Actions\RejectPipelineRunAction;
use App\Modules\Pipelines\Models\PipelineRun;
use App\Modules\Pipelines\Requests\RejectPipelineRunRequest;
use App\Modules\Pipelines\Resources\PipelineRunResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class PipelineRunController extends Controller
{
    public function approve(
        string $pipelineRun,
        Request $request,
        ApprovePipelineRunAction $action,
    ): PipelineRunResource {
        $run = $this->resolvePipelineRun($pipelineRun);
        $this->authorize('approve', $run);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $approved = $action->execute($run, $actor);

        return PipelineRunResource::make($approved->load('steps'));
    }

    public function reject(
        string $pipelineRun,
        RejectPipelineRunRequest $request,
        RejectPipelineRunAction $action,
    ): PipelineRunResource {
        $run = $this->resolvePipelineRun($pipelineRun);
        $this->authorize('reject', $run);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $rejected = $action->execute($run, $actor, $request->reason());

        return PipelineRunResource::make($rejected->load('steps'));
    }

    private function resolvePipelineRun(string $pipelineRunId): PipelineRun
    {
        $run = PipelineRun::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($pipelineRunId)
            ->first();

        if ($run === null) {
            throw (new ModelNotFoundException())->setModel(PipelineRun::class, [$pipelineRunId]);
        }

        return $run;
    }
}
