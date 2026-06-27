<?php

declare(strict_types=1);

namespace App\Modules\Sites\Resources;

use App\Modules\BuildRunners\Enums\BuildStrategy;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Enums\GitProvider;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Enums\SslProvider;
use App\Modules\Sites\Enums\SslStatus;
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
            'preBuildScript' => $this->pre_build_script,
            'buildStrategy' => $this->build_strategy instanceof BuildStrategy
                ? $this->build_strategy->value
                : $this->build_strategy,
            'buildRunnerId' => $this->build_runner_id,
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
            'autoCreateDns' => $this->auto_create_dns,
            'isApex' => $this->is_apex,
            'projectDnsZoneId' => $this->project_dns_zone_id,
            'dnsZoneId' => $this->dns_zone_id,
            'dnsStatus' => $this->dns_status instanceof \App\Modules\Integrations\Enums\DnsStatus
                ? $this->dns_status->value
                : $this->dns_status,
            'dnsProvider' => $this->dns_provider,
            'dnsRecordIds' => $this->dns_record_ids ?? [],
            'dnsError' => $this->dns_error,
            'enableSsl' => $this->enable_ssl,
            'sslStatus' => $this->ssl_status instanceof SslStatus ? $this->ssl_status->value : $this->ssl_status,
            'sslProvider' => $this->ssl_provider instanceof SslProvider ? $this->ssl_provider->value : $this->ssl_provider,
            'sslError' => $this->ssl_error,
            'sslChallenge' => $this->ssl_challenge instanceof \App\Modules\Sites\Enums\SslChallenge
                ? $this->ssl_challenge->value
                : $this->ssl_challenge,
            'sslExpiresAt' => $this->ssl_expires_at?->toIso8601String(),
            'sslCheckedAt' => $this->ssl_checked_at?->toIso8601String(),
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
