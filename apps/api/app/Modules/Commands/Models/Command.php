<?php

declare(strict_types=1);

namespace App\Modules\Commands\Models;

use App\Modules\Commands\Enums\CommandStatus;
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
        'status',
        'timeout_seconds',
        'output',
        'exit_code',
        'executed_at',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommandStatus::class,
            'timeout_seconds' => 'integer',
            'exit_code' => 'integer',
            'executed_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function duration(): ?float
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return (float) $this->finished_at->floatDiffInSeconds($this->started_at);
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
