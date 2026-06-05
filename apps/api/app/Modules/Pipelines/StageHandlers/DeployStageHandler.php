<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\StageHandlers;

use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Jobs\RunDeploymentJob;
use App\Modules\Pipelines\Contracts\PipelineStageHandlerInterface;
use App\Modules\Pipelines\DTOs\PipelineExecutionContext;
use App\Modules\Pipelines\Enums\PipelineStageResult;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Pipelines\Exceptions\PipelineStageFailedException;
use App\Modules\Pipelines\Models\PipelineRunStep;

class DeployStageHandler implements PipelineStageHandlerInterface
{
    public function type(): PipelineStepType
    {
        return PipelineStepType::DEPLOY;
    }

    public function handle(PipelineExecutionContext $context, PipelineRunStep $step): PipelineStageResult
    {
        $deployment = $context->deployment;

        if ($deployment->status === DeploymentStatus::SUCCESS) {
            return PipelineStageResult::COMPLETED;
        }

        if ($deployment->status === DeploymentStatus::FAILED) {
            throw new PipelineStageFailedException(
                stepName: $step->name,
                message: 'Deployment stage failed.',
            );
        }

        if ($deployment->status === DeploymentStatus::PENDING) {
            RunDeploymentJob::dispatch(
                deploymentId: (string) $deployment->getKey(),
                actorId: $context->actorId,
            );

            return PipelineStageResult::PAUSED;
        }

        if (in_array($deployment->status, [DeploymentStatus::RUNNING, DeploymentStatus::AWAITING_APPROVAL], true)) {
            return PipelineStageResult::PAUSED;
        }

        throw new PipelineStageFailedException(
            stepName: $step->name,
            message: sprintf('Deployment is in an unexpected state: %s', $deployment->status->value),
        );
    }
}
