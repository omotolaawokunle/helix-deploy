<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDnsZone extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'project_dns_zones';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'project_id',
        'zone_id',
        'base_domain',
        'assigned_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
