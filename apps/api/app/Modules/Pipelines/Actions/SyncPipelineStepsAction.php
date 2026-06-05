<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Actions;

use App\Modules\Pipelines\DTOs\PipelineStepInputDTO;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStep;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SyncPipelineStepsAction
{
    /**
     * @param list<PipelineStepInputDTO> $steps
     */
    public function execute(Pipeline $pipeline, array $steps): void
    {
        $existingIds = $pipeline->steps()->pluck('id')->map(
            static fn (mixed $id): string => (string) $id,
        )->all();

        $retainedIds = [];

        foreach ($steps as $stepInput) {
            if ($stepInput->id !== null) {
                if (! in_array($stepInput->id, $existingIds, true)) {
                    throw ValidationException::withMessages([
                        'steps' => ['One or more step IDs do not belong to this pipeline.'],
                    ]);
                }

                $step = PipelineStep::query()->whereKey($stepInput->id)->firstOrFail();
                $step->forceFill([
                    'name' => $stepInput->name,
                    'type' => $stepInput->type,
                    'order' => $stepInput->order,
                    'config' => $stepInput->config,
                    'requires_approval' => $stepInput->requiresApproval,
                    'approver_role' => $stepInput->approverRole,
                    'retry_attempts' => $stepInput->retryAttempts,
                ])->save();

                $retainedIds[] = $stepInput->id;

                continue;
            }

            $created = PipelineStep::query()->create([
                'id' => (string) Str::uuid(),
                'pipeline_id' => (string) $pipeline->getKey(),
                'name' => $stepInput->name,
                'type' => $stepInput->type,
                'order' => $stepInput->order,
                'config' => $stepInput->config,
                'requires_approval' => $stepInput->requiresApproval,
                'approver_role' => $stepInput->approverRole,
                'retry_attempts' => $stepInput->retryAttempts,
            ]);

            $retainedIds[] = (string) $created->getKey();
        }

        $removeIds = array_values(array_diff($existingIds, $retainedIds));

        if ($removeIds !== []) {
            PipelineStep::query()->whereIn('id', $removeIds)->delete();
        }
    }
}
