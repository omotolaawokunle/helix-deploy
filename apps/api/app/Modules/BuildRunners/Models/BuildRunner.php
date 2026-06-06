<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Models;

use App\Models\User;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use App\Modules\Sites\Enums\Runtime;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildRunner extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'build_runners';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'name',
        'ip_address',
        'ssh_port',
        'ssh_user',
        'status',
        'max_concurrent_builds',
        'cpu_cores',
        'ram_gb',
        'supported_runtimes',
        'credential_id',
        'fingerprint',
        'project_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ssh_port' => 'integer',
            'max_concurrent_builds' => 'integer',
            'cpu_cores' => 'integer',
            'ram_gb' => 'integer',
            'status' => BuildRunnerStatus::class,
            'supported_runtimes' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(BuildArtifact::class, 'runner_id');
    }

    public function supportsRuntime(Runtime $runtime): bool
    {
        /** @var list<string> $supported */
        $supported = $this->supported_runtimes ?? [];

        return in_array($runtime->value, $supported, true);
    }

    public function isAvailable(): bool
    {
        return $this->status === BuildRunnerStatus::ONLINE;
    }

    public function connectionString(): string
    {
        return sprintf('%s@%s:%d', (string) $this->ssh_user, (string) $this->ip_address, (int) $this->ssh_port);
    }
}
