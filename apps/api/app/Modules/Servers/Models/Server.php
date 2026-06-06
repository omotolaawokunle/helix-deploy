<?php

declare(strict_types=1);

namespace App\Modules\Servers\Models;

use App\Modules\Commands\Models\Command;
use App\Modules\Credentials\Models\Credential;
use App\Modules\CronJobs\Models\CronJob;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Enums\ManagementMode;
use App\Modules\Servers\Enums\ServerProvider;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use App\Modules\Sites\Models\Site;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasUuids;
    use OwnedByOrganization;

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
        'provider_instance_id',
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
            'status' => ServerStatus::class,
            'provider' => ServerProvider::class,
            'management_mode' => ManagementMode::class,
            'tags' => 'array',
            'installed_services' => 'array',
            'health_status' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function cronJobs(): HasMany
    {
        return $this->hasMany(CronJob::class);
    }

    public function supervisorProcesses(): HasMany
    {
        return $this->hasMany(SupervisorProcess::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(Command::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function serverGroups(): BelongsToMany
    {
        return $this->belongsToMany(ServerGroup::class, 'server_group_server');
    }

    public function isProduction(): bool
    {
        return (bool) $this->environment?->is_production;
    }

    public function isManaged(): bool
    {
        return $this->management_mode === ManagementMode::MANAGED;
    }

    public function connectionString(): string
    {
        return sprintf('%s@%s:%d', (string) $this->ssh_user, (string) $this->ip_address, (int) $this->ssh_port);
    }
}
