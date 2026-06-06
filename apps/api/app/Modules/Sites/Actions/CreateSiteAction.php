<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Integrations\Contracts\SiteDnsProvisionerInterface;
use App\Modules\Integrations\DTOs\SiteDnsConfigurationDTO;
use App\Modules\Integrations\Enums\DnsStatus;
use App\Modules\Integrations\Exceptions\DnsRecordAlreadyExistsException;
use App\Modules\Integrations\Exceptions\SiteDnsValidationException;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Contracts\NginxConfigGeneratorInterface;
use App\Modules\Sites\Contracts\SiteSslProvisionerInterface;
use App\Modules\Sites\DTOs\CreateSiteDTO;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Enums\SslChallenge;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Events\SiteCreated;
use App\Modules\Sites\Events\SiteProvisioningFailed;
use App\Modules\Sites\Exceptions\NginxConfigInvalidException;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteNginxProvisioner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSiteAction
{
    public function __construct(
        private readonly NginxConfigGeneratorInterface $nginxConfigGenerator,
        private readonly SiteNginxProvisioner $siteNginxProvisioner,
        private readonly SiteDnsProvisionerInterface $siteDnsProvisioner,
        private readonly SiteSslProvisionerInterface $siteSslProvisioner,
    ) {
    }

    public function execute(Server $server, Organization $org, User $actor, CreateSiteDTO $dto): Site
    {
        if ((string) $server->organization_id !== (string) $org->getKey()) {
            throw new AuthorizationException('Server does not belong to this organization.');
        }

        $project = $this->resolveProject($dto->projectId, $org);
        $environment = $this->resolveEnvironment($dto->environmentId, $org, $project);

        $dnsConfiguration = new SiteDnsConfigurationDTO(
            autoCreateDns: $dto->autoCreateDns,
            includeWwwAlias: $dto->includeWwwAlias,
            projectDnsZoneId: $dto->projectDnsZoneId,
            domain: $dto->domain,
            aliases: $dto->aliases,
            projectId: $dto->projectId,
        );

        try {
            $this->siteDnsProvisioner->validateForCreate($org, $dnsConfiguration);
        } catch (SiteDnsValidationException|DnsRecordAlreadyExistsException $exception) {
            throw ValidationException::withMessages([
                'domain' => [$exception->getMessage()],
            ]);
        }

        $dnsAttributes = $this->siteDnsProvisioner->resolveSiteAttributes($org, $dnsConfiguration);

        if (($dnsAttributes['is_apex'] ?? false) === true) {
            $this->assertApexAvailable($org, (string) ($dnsAttributes['dns_zone_id'] ?? ''), $dto->domain);
        }

        $aliases = $dnsAttributes['aliases'] ?? $dto->aliases;
        unset($dnsAttributes['aliases']);

        return Site::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'server_id' => (string) $server->getKey(),
            'organization_id' => (string) $org->getKey(),
            'project_id' => $project?->getKey(),
            'environment_id' => $environment?->getKey(),
            'domain' => $dto->domain,
            'aliases' => $aliases,
            'webroot' => $dto->webroot,
            'runtime' => $dto->runtime->value,
            'deploy_mode' => $dto->deployMode->value,
            'repository_url' => $dto->repositoryUrl,
            'repository_provider' => $dto->repositoryProvider,
            'deploy_branch' => $dto->deployBranch,
            'pre_deploy_script' => $dto->preDeployScript,
            'post_deploy_script' => $dto->postDeployScript,
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
            'enable_ssl' => $dto->enableSsl,
            'ssl_status' => $dto->enableSsl ? SslStatus::PENDING->value : SslStatus::NONE->value,
            'ssl_challenge' => $dto->enableSsl ? ($dto->sslChallenge?->value ?? SslChallenge::HTTP_01->value) : null,
            'status' => SiteStatus::PROVISIONING->value,
        ], $dnsAttributes));
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

        if ($site->auto_create_dns) {
            $this->siteDnsProvisioner->provision($site->refresh());
        }

        if ($site->enable_ssl) {
            $this->siteSslProvisioner->issue($site->refresh());
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
                'dnsStatus' => $site->dns_status instanceof DnsStatus ? $site->dns_status->value : $site->dns_status,
                'sslStatus' => $site->ssl_status instanceof SslStatus ? $site->ssl_status->value : $site->ssl_status,
            ],
        );

        event(new SiteCreated($site));

        return $site;
    }

    private function assertApexAvailable(
        Organization $org,
        string $dnsZoneId,
        string $domain,
    ): void {
        if ($dnsZoneId === '') {
            return;
        }

        $exists = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $org->getKey())
            ->where('is_apex', true)
            ->where('dns_zone_id', $dnsZoneId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'domain' => [sprintf(
                    'An apex site already exists for zone containing [%s].',
                    $domain,
                )],
            ]);
        }
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
