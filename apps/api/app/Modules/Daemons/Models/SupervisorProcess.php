<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Models;

use App\Models\User;
use App\Modules\Daemons\Enums\DaemonStatus;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisorProcess extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'supervisor_processes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'server_id',
        'organization_id',
        'name',
        'command',
        'directory',
        'user',
        'processes',
        'status',
        'config_path',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DaemonStatus::class,
            'processes' => 'integer',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
