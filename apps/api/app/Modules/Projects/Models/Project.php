<?php

declare(strict_types=1);

namespace App\Modules\Projects\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Servers\Models\Server;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'projects';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_team');
    }
}
