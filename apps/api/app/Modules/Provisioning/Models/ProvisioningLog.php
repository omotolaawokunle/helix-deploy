<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningLog extends Model
{
    use HasUuids;

    protected $table = 'provisioning_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'server_id',
        'organization_id',
        'run_id',
        'line',
        'created_at',
    ];

    public $timestamps = false;

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
