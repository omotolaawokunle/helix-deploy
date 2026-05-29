<?php

declare(strict_types=1);

namespace App\Modules\Projects\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Environment extends Model
{
    use HasUuids;

    protected $table = 'environments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'project_id',
        'organization_id',
        'name',
        'label',
        'is_production',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_production' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
