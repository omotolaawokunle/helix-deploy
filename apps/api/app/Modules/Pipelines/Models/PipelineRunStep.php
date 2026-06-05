<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Models;

use App\Modules\Pipelines\Enums\PipelineRunStepStatus;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineRunStep extends Model
{
    use HasUuids;

    protected $table = 'pipeline_run_steps';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'pipeline_run_id',
        'pipeline_step_id',
        'name',
        'type',
        'order',
        'status',
        'config',
        'requires_approval',
        'approver_role',
        'retry_attempts',
        'attempts_made',
        'exit_code',
        'output',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PipelineStepType::class,
            'status' => PipelineRunStepStatus::class,
            'approver_role' => TeamRole::class,
            'order' => 'integer',
            'retry_attempts' => 'integer',
            'attempts_made' => 'integer',
            'requires_approval' => 'boolean',
            'config' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function pipelineRun(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class);
    }

    public function pipelineStep(): BelongsTo
    {
        return $this->belongsTo(PipelineStep::class);
    }
}
