<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Models;

use App\Models\User;
use App\Modules\BuildRunners\Enums\BuildStrategy;
use App\Modules\BuildRunners\Models\BuildArtifact;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Deployment extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'deployments';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'build_strategy' => 'on_server',
    ];

    protected $fillable = [
        'site_id',
        'organization_id',
        'type',
        'status',
        'triggered_by',
        'trigger_type',
        'branch',
        'commit_hash',
        'commit_message',
        'release_path',
        'rollback_target_id',
        'rollback_reason',
        'pipeline_run_id',
        'build_runner_id',
        'build_artifact_id',
        'build_strategy',
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
            'status' => DeploymentStatus::class,
            'type' => DeploymentType::class,
            'trigger_type' => TriggerType::class,
            'build_strategy' => BuildStrategy::class,
            'metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(DeploymentStep::class)->orderBy('order');
    }

    public function buildRunner(): BelongsTo
    {
        return $this->belongsTo(BuildRunner::class);
    }

    public function buildArtifact(): HasOne
    {
        return $this->hasOne(BuildArtifact::class);
    }

    public function rollbackTarget(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rollback_target_id');
    }

    public function isRunnerBuild(): bool
    {
        return $this->build_strategy === BuildStrategy::RUNNER;
    }

    public function isOnServerBuild(): bool
    {
        return $this->build_strategy === BuildStrategy::ON_SERVER;
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function duration(): ?float
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return (float) $this->finished_at->floatDiffInSeconds($this->started_at);
    }

    public function isRollbackable(): bool
    {
        return $this->type === DeploymentType::DEPLOY
            && $this->status === DeploymentStatus::SUCCESS;
    }

    public function currentStep(): ?DeploymentStep
    {
        $runningStep = $this->steps()->where('status', DeploymentStepStatus::RUNNING->value)->first();

        if ($runningStep !== null) {
            return $runningStep;
        }

        return $this->steps()
            ->whereIn('status', [DeploymentStepStatus::PENDING->value, DeploymentStepStatus::FAILED->value])
            ->orderBy('order')
            ->first();
    }
}
