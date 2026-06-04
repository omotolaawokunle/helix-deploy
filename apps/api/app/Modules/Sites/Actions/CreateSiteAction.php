<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Contracts\NginxConfigGeneratorInterface;
use App\Modules\Sites\DTOs\CreateSiteDTO;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Events\SiteCreated;
use App\Modules\Sites\Events\SiteProvisioningFailed;
use App\Modules\Sites\Exceptions\NginxConfigInvalidException;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteNginxProvisioner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class CreateSiteAction
{
    public function __construct(
        private readonly NginxConfigGeneratorInterface $nginxConfigGenerator,
        private readonly SiteNginxProvisioner $siteNginxProvisioner,
    ) {
    }

    public function execute(Server $server, Organization $org, User $actor, CreateSiteDTO $dto): Site
    {
        if ((string) $server->organization_id !== (string) $org->getKey()) {
            throw new AuthorizationException('Server does not belong to this organization.');
        }

        $project = $this->resolveProject($dto->projectId, $org);
        $environment = $this->resolveEnvironment($dto->environmentId, $org, $project);

        return Site::query()->create([
            'id' => (string) Str::uuid(),
            'server_id' => (string) $server->getKey(),
            'organization_id' => (string) $org->getKey(),
            'project_id' => $project?->getKey(),
            'environment_id' => $environment?->getKey(),
            'domain' => $dto->domain,
            'aliases' => $dto->aliases,
            'webroot' => $dto->webroot,
            'runtime' => $dto->runtime->value,
            'deploy_mode' => $dto->deployMode->value,
            'repository_url' => $dto->repositoryUrl,
            'repository_provider' => $dto->repositoryProvider,
            'deploy_branch' => $dto->deployBranch,
            'deploy_script' => $dto->deployScript,
            'run_migrations' => $dto->runMigrations,
            'docker_image' => $dto->dockerImage,
            'docker_registry' => $dto->dockerRegistry,
            'docker_compose_path' => $dto->dockerComposePath,
            'docker_build_mode' => $dto->dockerBuildMode?->value,
            'php_version' => $dto->phpVersion,
            'node_pm' => $dto->nodePm?->value,
            'python_wsgi' => $dto->pythonWsgi?->value,
            'go_binary_path' => $dto->goBinaryPath,
            'go_service_name' => $dto->goServiceName,
            'app_port' => $dto->appPort,
            'pipeline_id' => $dto->pipelineId,
            'status' => SiteStatus::PROVISIONING->value,
        ]);
    }

    public function provision(Site $site): Site
    {
        $server = $site->server;
        abort_if($server === null, 404);

        try {
            $this->siteNginxProvisioner->createWebroot($server, $site->domain);
            $config = $this->nginxConfigGenerator->generate($site);
            $this->siteNginxProvisioner->apply($server, $site, $config);
        } catch (NginxConfigInvalidException $exception) {
            $this->siteNginxProvisioner->rollbackConfig($server, $site->domain);

            event(new SiteProvisioningFailed(
                siteId: (string) $site->getKey(),
                serverId: (string) $server->getKey(),
                organizationId: (string) $site->organization_id,
                domain: $site->domain,
                message: $exception->nginxTestOutput,
                siteRemoved: true,
            ));

            $site->delete();

            throw $exception;
        }

        $site->forceFill(['status' => SiteStatus::ACTIVE->value])->save();

        $site = $site->refresh();

        AuditLog::record(
            operation: 'site.created',
            resource: $site,
            beforeState: null,
            afterState: [
                'domain' => $site->domain,
                'runtime' => $site->runtime->value,
                'serverId' => $site->server_id,
                'status' => SiteStatus::ACTIVE->value,
            ],
        );

        event(new SiteCreated($site));

        return $site;
    }

    private function resolveProject(?string $projectId, Organization $org): ?Project
    {
        if ($projectId === null) {
            return null;
        }

        $project = Project::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($projectId)
            ->where('organization_id', (string) $org->getKey())
            ->first();

        if ($project === null) {
            throw (new ModelNotFoundException())->setModel(Project::class, [$projectId]);
        }

        return $project;
    }

    private function resolveEnvironment(?string $environmentId, Organization $org, ?Project $project): ?Environment
    {
        if ($environmentId === null) {
            return null;
        }

        $query = Environment::query()
            ->whereKey($environmentId)
            ->where('organization_id', (string) $org->getKey());

        if ($project !== null) {
            $query->where('project_id', (string) $project->getKey());
        }

        $environment = $query->first();

        if ($environment === null) {
            throw (new ModelNotFoundException())->setModel(Environment::class, [$environmentId]);
        }

        return $environment;
    }
}
