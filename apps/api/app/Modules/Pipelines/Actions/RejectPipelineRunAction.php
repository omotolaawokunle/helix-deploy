<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Pipelines\Enums\PipelineRunStatus;
use App\Modules\Pipelines\Enums\PipelineRunStepStatus;
use App\Modules\Pipelines\Events\PipelineRejected;
use App\Modules\Pipelines\Models\PipelineRun;
use Illuminate\Validation\ValidationException;

class RejectPipelineRunAction
{
    public function execute(PipelineRun $run, User $actor, ?string $reason = null): PipelineRun
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
            'status' => PipelineRunStepStatus::FAILED,
            'output' => $reason ?? 'Approval rejected.',
            'finished_at' => now(),
        ])->save();

        $run->forceFill([
            'status' => PipelineRunStatus::FAILED,
            'finished_at' => now(),
        ])->save();

        $deployment = $run->deployment;

        if ($deployment !== null && ! $deployment->status->isTerminal()) {
            $deployment->forceFill([
                'status' => DeploymentStatus::FAILED,
                'finished_at' => now(),
            ])->save();
        }

        AuditLog::record(
            operation: 'pipeline_run.rejected',
            resource: $run,
            metadata: [
                'actor_id' => (string) $actor->getKey(),
                'reason' => $reason,
            ],
            beforeState: $beforeState,
            afterState: [
                'status' => PipelineRunStatus::FAILED->value,
                'stepId' => (string) $step->getKey(),
            ],
        );

        event(new PipelineRejected($run->refresh(), (string) $actor->getKey(), $reason));

        return $run;
    }
}
