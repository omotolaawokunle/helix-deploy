<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Auth\Contracts\AuthServiceInterface;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Contracts\ServerInventoryIntrospectorInterface;
use App\Modules\Servers\Services\ServerInventoryIntrospector;
use App\Modules\Sites\Contracts\DiscoveredSiteImporterInterface;
use App\Modules\Sites\Contracts\NginxConfigGeneratorInterface;
use App\Modules\Sites\Actions\ImportDiscoveredSitesAction;
use App\Modules\Sites\Services\NginxConfigGenerator;
use App\Packages\Execution\Contracts\ExecutionRunnerInterface;
use App\Packages\Execution\DeploymentRunner;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Daemons\Policies\DaemonPolicy;
use App\Modules\Pipelines\Contracts\PipelineStageHandlerInterface;
use App\Modules\Pipelines\Services\PipelineStageHandlerRegistry;
use App\Modules\Pipelines\StageHandlers\ApproveStageHandler;
use App\Modules\Pipelines\StageHandlers\DeployStageHandler;
use App\Modules\Pipelines\StageHandlers\HealthCheckStageHandler;
use App\Modules\Pipelines\StageHandlers\MigrateStageHandler;
use App\Modules\Pipelines\StageHandlers\NotifyStageHandler;
use App\Modules\Pipelines\StageHandlers\ScriptStageHandler;
use App\Modules\Monitoring\Contracts\ServerMetricsCollectorInterface;
use App\Modules\Monitoring\Services\ServerMetricsCollector;
use App\Modules\Integrations\Contracts\CloudflareClientInterface;
use App\Modules\Integrations\Contracts\SiteDnsProvisionerInterface;
use App\Modules\Integrations\Models\CloudflareIntegration;
use App\Modules\Integrations\Models\DigitalOceanIntegration;
use App\Modules\Integrations\Models\ProjectDnsZone;
use App\Modules\Integrations\Policies\CloudflarePolicy;
use App\Modules\Integrations\Policies\DigitalOceanPolicy;
use App\Modules\Integrations\Policies\ProjectDnsZonePolicy;
use App\Modules\Integrations\Services\Cloudflare\CloudflareClient;
use App\Modules\Integrations\Services\DigitalOcean\DigitalOceanDnsClient;
use App\Modules\Integrations\Services\DnsProviderRegistry;
use App\Modules\Integrations\Services\SiteDnsProvisioner;
use App\Modules\Sites\Contracts\SiteSslProvisionerInterface;
use App\Modules\Sites\Services\SiteSslProvisioner;
use App\Modules\Servers\Models\CloudProviderIntegration;
use App\Modules\Servers\Policies\CloudProviderPolicy;
use App\Modules\Sites\Models\GitProviderIntegration;
use App\Modules\Sites\Policies\GitProviderPolicy;
use App\Modules\Teams\Contracts\TeamProjectVisibilityServiceInterface;
use App\Modules\Teams\Services\TeamProjectVisibilityService;
use App\Modules\BuildRunners\Contracts\RunnerSlotStoreInterface;
use App\Modules\BuildRunners\Services\RedisRunnerSlotStore;
use App\Packages\Realtime\DeploymentStreamPublisher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuthServiceInterface::class, AuthService::class);
        $this->app->singleton(CredentialVaultInterface::class, CredentialVault::class);
        $this->app->singleton(NginxConfigGeneratorInterface::class, NginxConfigGenerator::class);
        $this->app->singleton(ServerInventoryIntrospectorInterface::class, ServerInventoryIntrospector::class);
        $this->app->singleton(DiscoveredSiteImporterInterface::class, ImportDiscoveredSitesAction::class);
        $this->app->singleton(ExecutionRunnerInterface::class, DeploymentRunner::class);
        $this->app->singleton(DeploymentStreamPublisher::class);
        $this->app->singleton(TeamProjectVisibilityServiceInterface::class, TeamProjectVisibilityService::class);
        $this->app->singleton(ServerMetricsCollectorInterface::class, ServerMetricsCollector::class);
        $this->app->singleton(RunnerSlotStoreInterface::class, RedisRunnerSlotStore::class);
        $this->app->singleton(CloudflareClientInterface::class, CloudflareClient::class);
        $this->app->singleton(DigitalOceanDnsClient::class);
        $this->app->singleton(DnsProviderRegistry::class);
        $this->app->singleton(SiteDnsProvisionerInterface::class, SiteDnsProvisioner::class);
        $this->app->singleton(SiteSslProvisionerInterface::class, SiteSslProvisioner::class);
        $this->app->singleton(PipelineStageHandlerRegistry::class, function (): PipelineStageHandlerRegistry {
            /** @var list<PipelineStageHandlerInterface> $handlers */
            $handlers = [
                new DeployStageHandler(),
                new MigrateStageHandler(),
                new HealthCheckStageHandler(),
                new ScriptStageHandler(),
                new ApproveStageHandler(),
                new NotifyStageHandler(),
            ];

            return new PipelineStageHandlerRegistry($handlers);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(SupervisorProcess::class, DaemonPolicy::class);
        Gate::policy(GitProviderIntegration::class, GitProviderPolicy::class);
        Gate::policy(CloudProviderIntegration::class, CloudProviderPolicy::class);
        Gate::policy(CloudflareIntegration::class, CloudflarePolicy::class);
        Gate::policy(DigitalOceanIntegration::class, DigitalOceanPolicy::class);
        Gate::policy(ProjectDnsZone::class, ProjectDnsZonePolicy::class);
    }
}
