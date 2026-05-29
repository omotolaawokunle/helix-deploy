<?php

declare(strict_types=1);

namespace App\Modules\Servers\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Server extends Model
{
    use HasUuids;

    protected $table = 'servers';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'project_id',
        'environment_id',
        'hostname',
        'ip_address',
        'ssh_port',
        'ssh_user',
        'provider',
        'region',
        'server_type',
        'os',
        'php_version',
        'node_version',
        'status',
        'management_mode',
        'fingerprint',
        'credential_id',
        'tags',
        'installed_services',
        'health_status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ssh_port' => 'integer',
            'tags' => 'array',
            'installed_services' => 'array',
            'health_status' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
