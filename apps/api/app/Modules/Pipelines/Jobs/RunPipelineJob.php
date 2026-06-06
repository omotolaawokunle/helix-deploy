<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Jobs;

use App\Models\User;
use App\Modules\Pipelines\Models\PipelineRun;
use App\Modules\Pipelines\Services\PipelineStageOrchestrator;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;

class RunPipelineJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public readonly string $pipelineRunId,
        public readonly string $actorId,
        public readonly int $startStepIndex = 0,
    ) {
        $this->onQueue('deployments');
    }

    public function uniqueId(): string
    {
        return 'pipeline_run_'.$this->pipelineRunId;
    }

    public function handle(PipelineStageOrchestrator $orchestrator): void
    {
        $actor = User::query()->find($this->actorId);

        if ($actor !== null) {
            Auth::setUser($actor);
        }

        $run = PipelineRun::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->pipelineRunId)
            ->firstOrFail();

        $orchestrator->run($run, $this->actorId, $this->startStepIndex);
    }
}
