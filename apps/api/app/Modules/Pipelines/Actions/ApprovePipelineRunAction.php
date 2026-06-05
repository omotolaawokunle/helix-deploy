<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Pipelines\Enums\PipelineRunStatus;
use App\Modules\Pipelines\Enums\PipelineRunStepStatus;
use App\Modules\Pipelines\Events\PipelineApproved;
use App\Modules\Pipelines\Jobs\RunPipelineJob;
use App\Modules\Pipelines\Models\PipelineRun;
use Illuminate\Validation\ValidationException;

class ApprovePipelineRunAction
{
    public function execute(PipelineRun $run, User $actor): PipelineRun
    {
        if ($run->status !== PipelineRunStatus::AWAITING_APPROVAL) {
            throw ValidationException::withMessages([
                'pipelineRun' => 'This pipeline run is not awaiting approval.',
            ]);
        }

        $step = $run->awaitingApprovalStep();

        if ($step === null) {
            throw ValidationException::withMessages([
                'pipelineRun' => 'No approval stage is currently pending.',
            ]);
        }

        $beforeState = [
            'status' => $run->status->value,
            'stepId' => (string) $step->getKey(),
        ];

        $step->forceFill([
            'status' => PipelineRunStepStatus::SUCCESS,
            'exit_code' => 0,
            'finished_at' => now(),
        ])->save();

        $run->forceFill([
            'status' => PipelineRunStatus::RUNNING,
        ])->save();

        $deployment = $run->deployment;

        if ($deployment !== null && $deployment->status === DeploymentStatus::AWAITING_APPROVAL) {
            $deployment->forceFill([
                'status' => DeploymentStatus::RUNNING,
            ])->save();
        }

        AuditLog::record(
            operation: 'pipeline_run.approved',
            resource: $run,
            metadata: [
                'actor_id' => (string) $actor->getKey(),
            ],
            beforeState: $beforeState,
            afterState: [
                'status' => PipelineRunStatus::RUNNING->value,
                'stepId' => (string) $step->getKey(),
            ],
        );

        event(new PipelineApproved($run->refresh(), (string) $actor->getKey()));

        RunPipelineJob::dispatch(
            pipelineRunId: (string) $run->getKey(),
            actorId: (string) $actor->getKey(),
            startStepIndex: $step->order + 1,
        );

        return $run->refresh();
    }
}
