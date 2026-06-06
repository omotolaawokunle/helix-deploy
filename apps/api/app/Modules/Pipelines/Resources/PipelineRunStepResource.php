<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pipelines\Models\PipelineRunStep
 */
class PipelineRunStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'pipelineRunId' => (string) $this->pipeline_run_id,
            'name' => $this->name,
            'type' => $this->type->value,
            'order' => $this->order,
            'status' => $this->status->value,
            'config' => $this->config ?? [],
            'requiresApproval' => $this->requires_approval,
            'approverRole' => $this->approver_role?->value,
            'retryAttempts' => $this->retry_attempts,
            'attemptsMade' => $this->attempts_made,
            'exitCode' => $this->exit_code,
            'output' => $this->output,
            'startedAt' => $this->started_at?->toIso8601String(),
            'finishedAt' => $this->finished_at?->toIso8601String(),
        ];
    }
}
