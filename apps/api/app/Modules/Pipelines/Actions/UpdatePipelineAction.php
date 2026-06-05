<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Pipelines\DTOs\UpdatePipelineDTO;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePipelineAction
{
    public function __construct(
        private readonly SyncPipelineStepsAction $syncPipelineStepsAction,
    ) {
    }

    public function execute(Pipeline $pipeline, UpdatePipelineDTO $dto): Pipeline
    {
        if ($dto->hasProjectId) {
            $this->assertProjectBelongsToOrganization($pipeline, $dto->projectId);
        }

        return DB::transaction(function () use ($pipeline, $dto): Pipeline {
            $beforeState = $this->auditState($pipeline->load('steps'));

            if ($dto->name !== null) {
                $pipeline->name = $dto->name;
            }

            if ($dto->hasDescription) {
                $pipeline->description = $dto->description;
            }

            if ($dto->hasProjectId) {
                $pipeline->project_id = $dto->projectId;
            }

            $pipeline->save();

            if ($dto->steps !== null) {
                $this->syncPipelineStepsAction->execute($pipeline, $dto->steps);
            }

            $pipeline = $pipeline->refresh()->load('steps');

            AuditLog::record(
                operation: 'pipeline.updated',
                resource: $pipeline,
                beforeState: $beforeState,
                afterState: $this->auditState($pipeline),
            );

            return $pipeline;
        });
    }

    private function assertProjectBelongsToOrganization(Pipeline $pipeline, ?string $projectId): void
    {
        if ($projectId === null) {
            return;
        }

        $exists = Project::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $pipeline->organization_id)
            ->whereKey($projectId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'projectId' => ['The selected project does not belong to this organization.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function auditState(Pipeline $pipeline): array
    {
        return [
            'name' => $pipeline->name,
            'description' => $pipeline->description,
            'projectId' => $pipeline->project_id,
            'stepCount' => $pipeline->steps->count(),
        ];
    }
}
