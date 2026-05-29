<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Models;

use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineStep extends Model
{
    use HasUuids;

    protected $table = 'pipeline_steps';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'pipeline_id',
        'name',
        'type',
        'order',
        'config',
        'requires_approval',
        'approver_role',
        'retry_attempts',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PipelineStepType::class,
            'approver_role' => TeamRole::class,
            'order' => 'integer',
            'retry_attempts' => 'integer',
            'requires_approval' => 'boolean',
            'config' => 'array',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }
}
