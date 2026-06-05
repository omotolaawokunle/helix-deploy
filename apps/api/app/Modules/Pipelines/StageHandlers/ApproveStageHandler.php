<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\StageHandlers;

use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Events\DeploymentApprovalRequired;
use App\Modules\Pipelines\Contracts\PipelineStageHandlerInterface;
use App\Modules\Pipelines\DTOs\PipelineExecutionContext;
use App\Modules\Pipelines\Enums\PipelineRunStatus;
use App\Modules\Pipelines\Enums\PipelineRunStepStatus;
use App\Modules\Pipelines\Enums\PipelineStageResult;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Pipelines\Models\PipelineRunStep;
use App\Modules\Teams\Enums\TeamRole;

class ApproveStageHandler implements PipelineStageHandlerInterface
{
    public function type(): PipelineStepType
    {
        return PipelineStepType::APPROVE;
    }

    public function handle(PipelineExecutionContext $context, PipelineRunStep $step): PipelineStageResult
    {
        $approverRole = $step->approver_role ?? TeamRole::ADMIN;
        $reason = sprintf(
            'Pipeline stage "%s" requires approval from %s or higher.',
            $step->name,
            $approverRole->label(),
        );

        $step->forceFill([
            'status' => PipelineRunStepStatus::AWAITING_APPROVAL,
            'finished_at' => null,
        ])->save();

        $context->run->forceFill([
            'status' => PipelineRunStatus::AWAITING_APPROVAL,
            'current_step_order' => $step->order,
        ])->save();

        $context->deployment->forceFill([
            'status' => DeploymentStatus::AWAITING_APPROVAL,
        ])->save();

        event(new DeploymentApprovalRequired($context->deployment->refresh(), $reason));

        return PipelineStageResult::PAUSED;
    }
}
