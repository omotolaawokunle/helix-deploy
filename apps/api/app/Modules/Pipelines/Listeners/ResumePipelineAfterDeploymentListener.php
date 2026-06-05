<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Listeners;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Events\DeploymentCompleted;
use App\Modules\Pipelines\Enums\PipelineRunStatus;
use App\Modules\Pipelines\Enums\PipelineRunStepStatus;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Pipelines\Jobs\RunPipelineJob;
use App\Modules\Pipelines\Models\PipelineRun;
use App\Modules\Pipelines\Models\PipelineRunStep;

class ResumePipelineAfterDeploymentListener
{
    public function handle(DeploymentCompleted $event): void
    {
        $deployment = $event->deployment;

        if ($deployment->pipeline_run_id === null) {
            return;
        }

        $run = PipelineRun::query()
            ->withoutGlobalScope('owned_by_organization')
            ->with('steps')
            ->whereKey((string) $deployment->pipeline_run_id)
            ->first();

        if ($run === null || $run->status->isTerminal()) {
            return;
        }

        $deployStep = $run->steps
            ->first(fn (PipelineRunStep $step): bool => $step->type === PipelineStepType::DEPLOY
                && $step->status === PipelineRunStepStatus::RUNNING);

        if ($deployStep === null) {
            return;
        }

        if ($deployment->status === DeploymentStatus::SUCCESS) {
            $deployStep->forceFill([
                'status' => PipelineRunStepStatus::SUCCESS,
                'exit_code' => 0,
                'finished_at' => now(),
            ])->save();

            $nextIndex = $deployStep->order + 1;

            RunPipelineJob::dispatch(
                pipelineRunId: (string) $run->getKey(),
                actorId: (string) $deployment->triggered_by,
                startStepIndex: $nextIndex,
            );

            return;
        }

        if ($deployment->status === DeploymentStatus::FAILED) {
            $deployStep->forceFill([
                'status' => PipelineRunStepStatus::FAILED,
                'finished_at' => now(),
            ])->save();

            $run->forceFill([
                'status' => PipelineRunStatus::FAILED,
                'finished_at' => now(),
            ])->save();

            AuditLog::record(
                operation: 'pipeline_run.failed',
                resource: $run,
                afterState: [
                    'status' => PipelineRunStatus::FAILED->value,
                    'reason' => 'deployment_failed',
                    'deploymentId' => $deployment->getKey(),
                ],
            );
        }
    }
}
