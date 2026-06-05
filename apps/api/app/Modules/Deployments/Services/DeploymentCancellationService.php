<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Services;

use Illuminate\Support\Facades\Cache;

final class DeploymentCancellationService
{
    private const TTL_SECONDS = 3600;

    public function request(string $deploymentId): void
    {
        Cache::put($this->key($deploymentId), true, self::TTL_SECONDS);
    }

    public function isRequested(string $deploymentId): bool
    {
        return Cache::get($this->key($deploymentId), false) === true;
    }

    public function clear(string $deploymentId): void
    {
        Cache::forget($this->key($deploymentId));
    }

    private function key(string $deploymentId): string
    {
        return 'deployment:cancel:'.$deploymentId;
    }
}
