<?php

declare(strict_types=1);

namespace App\Modules\Sites\Models;

use App\Modules\Credentials\Models\Credential;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\Release;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Models\Server;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Enums\NodePM;
use App\Modules\Sites\Enums\PythonWSGI;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Site extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'sites';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'server_id',
        'organization_id',
        'project_id',
        'environment_id',
        'domain',
        'aliases',
        'webroot',
        'runtime',
        'deploy_mode',
        'repository_url',
        'repository_provider',
        'deploy_branch',
        'pre_deploy_script',
        'post_deploy_script',
        'run_migrations',
        'docker_image',
        'docker_registry',
        'docker_compose_path',
        'docker_build_mode',
        'php_version',
        'node_pm',
        'python_wsgi',
        'go_binary_path',
        'go_service_name',
        'app_port',
        'status',
        'pipeline_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'runtime' => Runtime::class,
            'deploy_mode' => DeployMode::class,
            'docker_build_mode' => DockerBuildMode::class,
            'node_pm' => NodePM::class,
            'python_wsgi' => PythonWSGI::class,
            'status' => SiteStatus::class,
            'aliases' => 'array',
            'run_migrations' => 'boolean',
            'app_port' => 'integer',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function credentials(): MorphMany
    {
        return $this->morphMany(Credential::class, 'credentialable');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }
}
