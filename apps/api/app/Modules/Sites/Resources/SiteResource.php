<?php

declare(strict_types=1);

namespace App\Modules\Sites\Resources;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Enums\GitProvider;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Services\GitProviderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Sites\Models\Site
 */
class SiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'organizationId' => $this->organization_id,
            'serverId' => $this->server_id,
            'projectId' => $this->project_id,
            'environmentId' => $this->environment_id,
            'domain' => $this->domain,
            'aliases' => $this->aliases ?? [],
            'webroot' => $this->webroot,
            'runtime' => $this->runtime->value,
            'deployMode' => $this->deploy_mode->value,
            'repositoryUrl' => $this->repository_url,
            'repositoryProvider' => $this->repository_provider,
            'gitCredentialConfigured' => $this->gitCredentialConfigured(),
            'deployBranch' => $this->deploy_branch,
            'preDeployScript' => $this->pre_deploy_script,
            'postDeployScript' => $this->post_deploy_script,
            'runMigrations' => $this->run_migrations,
            'dockerImage' => $this->docker_image,
            'dockerRegistry' => $this->docker_registry,
            'dockerComposePath' => $this->docker_compose_path,
            'dockerBuildMode' => $this->docker_build_mode?->value,
            'phpVersion' => $this->php_version,
            'nodePm' => $this->node_pm?->value,
            'pythonWsgi' => $this->python_wsgi?->value,
            'goBinaryPath' => $this->go_binary_path,
            'goServiceName' => $this->go_service_name,
            'appPort' => $this->app_port,
            'pipelineId' => $this->pipeline_id,
            'status' => $this->status instanceof SiteStatus ? $this->status->value : $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function gitCredentialConfigured(): bool
    {
        $providerValue = $this->repository_provider;

        if ($providerValue === null || $providerValue === '') {
            return false;
        }

        $provider = GitProvider::tryFrom((string) $providerValue);

        if ($provider === null) {
            return false;
        }

        $organization = $this->organization;

        if (! $organization instanceof Organization && $this->organization_id !== null) {
            $organization = Organization::query()->find($this->organization_id);
        }

        if (! $organization instanceof Organization) {
            return false;
        }

        return app(GitProviderService::class)->hasProviderToken($organization, $provider);
    }
}
