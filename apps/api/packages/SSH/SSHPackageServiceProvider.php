<?php

declare(strict_types=1);

namespace App\Packages\SSH;

use App\Packages\SSH\Contracts\SSHConnectionInterface;
use Illuminate\Support\ServiceProvider;

class SSHPackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SSHManager::class, SSHManager::class);

        if ($this->app->environment('testing')) {
            $this->app->bind(SSHConnectionInterface::class, FakeSSHConnection::class);
        }
    }
}
