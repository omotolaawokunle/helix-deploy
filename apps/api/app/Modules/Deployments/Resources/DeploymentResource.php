<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Resources;

use App\Modules\BuildRunners\Enums\BuildStrategy;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\TriggerType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Deployments\Models\Deployment
 */
class DeploymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'organizationId' => $this->organization_id,
            'siteId' => $this->site_id,
            'type' => $this->type instanceof DeploymentType ? $this->type->value : $this->type,
            'status' => $this->status instanceof DeploymentStatus ? $this->status->value : $this->status,
            'triggerType' => $this->trigger_type instanceof TriggerType
                ? $this->trigger_type->value
                : $this->trigger_type,
            'branch' => $this->branch,
            'commitHash' => $this->commit_hash,
            'commitMessage' => $this->commit_message,
            'releasePath' => $this->release_path,
            'pipelineRunId' => $this->pipeline_run_id,
            'buildStrategy' => $this->build_strategy instanceof BuildStrategy
                ? $this->build_strategy->value
                : $this->build_strategy,
            'buildRunnerId' => $this->build_runner_id,
            'buildArtifactId' => $this->build_artifact_id,
            'isRollbackable' => $this->isRollbackable(),
            'triggeredBy' => $this->whenLoaded('triggeredBy', fn () => [
                'id' => (string) $this->triggeredBy?->getKey(),
                'name' => $this->triggeredBy?->name,
            ]),
            'startedAt' => $this->started_at?->toIso8601String(),
            'finishedAt' => $this->finished_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'steps' => DeploymentStepResource::collection($this->whenLoaded('steps')),
        ];
    }
}
