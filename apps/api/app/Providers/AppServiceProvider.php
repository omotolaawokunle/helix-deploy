<?php

namespace App\Providers;

use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\CredentialVault;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CredentialVaultInterface::class, CredentialVault::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
