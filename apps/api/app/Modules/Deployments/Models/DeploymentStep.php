<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Models;

use App\Modules\Deployments\Enums\DeploymentStepStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class DeploymentStep extends Model
{
    use HasUuids;

    protected $table = 'deployment_steps';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'deployment_id',
        'name',
        'status',
        'output',
        'exit_code',
        'order',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeploymentStepStatus::class,
            'order' => 'integer',
            'exit_code' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    public function appendOutput(string $line): void
    {
        $quoted = DB::connection()->getPdo()->quote($line."\n");

        $this->newQueryWithoutScopes()
            ->whereKey($this->getKey())
            ->update([
                'output' => DB::raw("COALESCE(output, '') || {$quoted}"),
            ]);

        $this->setAttribute('output', ((string) $this->getAttribute('output')).$line."\n");
    }
}
