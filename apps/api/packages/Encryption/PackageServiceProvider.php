<?php

declare(strict_types=1);

namespace App\Packages\Encryption;

use App\Packages\Encryption\Contracts\EncryptionInterface;
use Illuminate\Support\ServiceProvider;

class PackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KeyGenerator::class, KeyGenerator::class);

        $this->app->singleton(EncryptionInterface::class, SodiumEncryption::class);

        $this->app->singleton(MasterKeyManager::class, MasterKeyManager::class);
    }
}
