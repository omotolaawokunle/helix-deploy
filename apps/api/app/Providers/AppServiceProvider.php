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
use App\Packages\Realtime\DeploymentStreamPublisher;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
