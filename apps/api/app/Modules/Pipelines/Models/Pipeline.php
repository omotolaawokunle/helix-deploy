<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Models;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'pipelines';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'project_id',
        'name',
        'description',
        'stages',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stages' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(PipelineStep::class)->orderBy('order');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
