<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Models;

use App\Modules\BuildRunners\Enums\ArtifactStorageType;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildArtifact extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'build_artifacts';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'deployment_id',
        'runner_id',
        'storage_type',
        'storage_path',
        'checksum',
        'size_bytes',
        'runtime',
        'created_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'storage_type' => ArtifactStorageType::class,
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(BuildRunner::class, 'runner_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function formattedSize(): string
    {
        if ($this->size_bytes === null) {
            return 'Unknown';
        }

        $bytes = $this->size_bytes;

        if ($bytes >= 1_073_741_824) {
            return sprintf('%.1f GB', $bytes / 1_073_741_824);
        }

        if ($bytes >= 1_048_576) {
            return sprintf('%.1f MB', $bytes / 1_048_576);
        }

        if ($bytes >= 1024) {
            return sprintf('%.1f KB', $bytes / 1024);
        }

        return sprintf('%d B', $bytes);
    }
}
