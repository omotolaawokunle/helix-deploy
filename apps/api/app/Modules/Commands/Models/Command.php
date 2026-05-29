<?php

declare(strict_types=1);

namespace App\Modules\Commands\Models;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Command extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'commands';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'server_id',
        'organization_id',
        'user_id',
        'command',
        'output',
        'exit_code',
        'executed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exit_code' => 'integer',
            'executed_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
