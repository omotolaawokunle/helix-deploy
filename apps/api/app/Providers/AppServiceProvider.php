<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Auth\Contracts\AuthServiceInterface;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Sites\Contracts\NginxConfigGeneratorInterface;
use App\Modules\Sites\Services\NginxConfigGenerator;
use App\Packages\Execution\Contracts\ExecutionRunnerInterface;
use App\Packages\Execution\DeploymentRunner;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Daemons\Policies\DaemonPolicy;
use App\Modules\Teams\Contracts\TeamProjectVisibilityServiceInterface;
use App\Modules\Teams\Services\TeamProjectVisibilityService;
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
        $this->app->singleton(ExecutionRunnerInterface::class, DeploymentRunner::class);
        $this->app->singleton(DeploymentStreamPublisher::class);
        $this->app->singleton(TeamProjectVisibilityServiceInterface::class, TeamProjectVisibilityService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(SupervisorProcess::class, DaemonPolicy::class);
    }
}
