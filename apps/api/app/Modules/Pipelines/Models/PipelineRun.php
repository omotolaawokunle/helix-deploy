<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Models;

use App\Models\User;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Pipelines\Enums\PipelineRunStatus;
use App\Modules\Pipelines\Enums\PipelineRunStepStatus;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineRun extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'pipeline_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'pipeline_id',
        'site_id',
        'deployment_id',
        'triggered_by',
        'status',
        'current_step_order',
        'metadata',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PipelineRunStatus::class,
            'current_step_order' => 'integer',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(PipelineRunStep::class)->orderBy('order');
    }

    public function awaitingApprovalStep(): ?PipelineRunStep
    {
        return $this->steps()
            ->where('status', PipelineRunStepStatus::AWAITING_APPROVAL->value)
            ->orderBy('order')
            ->first();
    }
}
