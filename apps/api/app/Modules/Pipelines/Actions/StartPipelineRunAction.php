<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\DTOs\TriggerDeploymentDTO;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Pipelines\Jobs\RunPipelineJob;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Pipelines\Enums\PipelineRunStatus;
use App\Modules\Pipelines\Enums\PipelineRunStepStatus;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineRun;
use App\Modules\Pipelines\Models\PipelineRunStep;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartPipelineRunAction
{
    public function execute(Site $site, User $actor, TriggerDeploymentDTO $dto): Deployment
    {
        if ($site->pipeline_id === null) {
            throw ValidationException::withMessages([
                'pipeline' => 'Site does not have a linked pipeline.',
            ]);
        }

        $pipeline = Pipeline::query()
            ->withoutGlobalScope('owned_by_organization')
            ->with('steps')
            ->whereKey((string) $site->pipeline_id)
            ->where('organization_id', (string) $site->organization_id)
            ->first();

        if ($pipeline === null) {
            throw ValidationException::withMessages([
                'pipeline' => 'Linked pipeline was not found in this organization.',
            ]);
        }

        if ($pipeline->steps->isEmpty()) {
            throw ValidationException::withMessages([
                'pipeline' => 'Pipeline must contain at least one stage before running.',
            ]);
        }

        return DB::transaction(function () use ($site, $actor, $dto, $pipeline): Deployment {
            $deployment = Deployment::query()->create([
                'id' => (string) Str::uuid(),
                'site_id' => (string) $site->getKey(),
                'organization_id' => (string) $site->organization_id,
                'type' => DeploymentType::DEPLOY,
                'status' => DeploymentStatus::PENDING,
                'triggered_by' => (string) $actor->getKey(),
                'trigger_type' => TriggerType::MANUAL,
                'branch' => $dto->branch ?? $site->deploy_branch,
            ]);

            $pipelineRun = PipelineRun::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => (string) $site->organization_id,
                'pipeline_id' => (string) $pipeline->getKey(),
                'site_id' => (string) $site->getKey(),
                'deployment_id' => (string) $deployment->getKey(),
                'triggered_by' => (string) $actor->getKey(),
                'status' => PipelineRunStatus::PENDING,
                'current_step_order' => 0,
                'metadata' => [],
            ]);

            $deployment->forceFill([
                'pipeline_run_id' => (string) $pipelineRun->getKey(),
            ])->save();

            foreach ($pipeline->steps as $templateStep) {
                PipelineRunStep::query()->create([
                    'id' => (string) Str::uuid(),
                    'pipeline_run_id' => (string) $pipelineRun->getKey(),
                    'pipeline_step_id' => (string) $templateStep->getKey(),
                    'name' => $templateStep->name,
                    'type' => $templateStep->type,
                    'order' => $templateStep->order,
                    'status' => PipelineRunStepStatus::PENDING,
                    'config' => $templateStep->config ?? [],
                    'requires_approval' => $templateStep->requires_approval,
                    'approver_role' => $templateStep->approver_role,
                    'retry_attempts' => $templateStep->retry_attempts,
                ]);
            }

            AuditLog::record(
                operation: 'pipeline_run.created',
                resource: $pipelineRun,
                afterState: [
                    'pipelineId' => $pipeline->getKey(),
                    'siteId' => $site->getKey(),
                    'deploymentId' => $deployment->getKey(),
                    'stepCount' => $pipeline->steps->count(),
                ],
            );

            AuditLog::record(
                operation: 'deployment.triggered',
                resource: $deployment,
                afterState: [
                    'siteId' => $site->getKey(),
                    'branch' => $deployment->branch,
                    'status' => DeploymentStatus::PENDING->value,
                    'pipelineRunId' => $pipelineRun->getKey(),
                ],
            );

            RunPipelineJob::dispatch(
                pipelineRunId: (string) $pipelineRun->getKey(),
                actorId: (string) $actor->getKey(),
            );

            return $deployment->refresh();
        });
    }
}
