<?php

declare(strict_types=1);

namespace App\Modules\Servers\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ServerGroup extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'server_groups';

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

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'server_group_server');
    }
}
