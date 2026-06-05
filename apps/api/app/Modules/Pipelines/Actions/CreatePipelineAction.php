<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Pipelines\DTOs\CreatePipelineDTO;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePipelineAction
{
    public function __construct(
        private readonly SyncPipelineStepsAction $syncPipelineStepsAction,
    ) {
    }

    public function execute(Organization $org, User $actor, CreatePipelineDTO $dto): Pipeline
    {
        $this->assertProjectBelongsToOrganization($org, $dto->projectId);

        return DB::transaction(function () use ($org, $actor, $dto): Pipeline {
            $pipeline = Pipeline::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => (string) $org->getKey(),
                'project_id' => $dto->projectId,
                'name' => $dto->name,
                'description' => $dto->description,
                'stages' => [],
                'created_by' => (string) $actor->getKey(),
            ]);

            if ($dto->steps !== []) {
                $this->syncPipelineStepsAction->execute($pipeline, $dto->steps);
            }

            AuditLog::record(
                operation: 'pipeline.created',
                resource: $pipeline,
                afterState: $this->auditState($pipeline->load('steps')),
            );

            return $pipeline->refresh()->load('steps');
        });
    }

    private function assertProjectBelongsToOrganization(Organization $org, ?string $projectId): void
    {
        if ($projectId === null) {
            return;
        }

        $exists = Project::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $org->getKey())
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
