<?php

declare(strict_types=1);

namespace App\Packages\Realtime;

use Illuminate\Support\Facades\Redis;

final class DeploymentStreamPublisher
{
    /**
     * @param array<string, mixed> $data
     */
    public function publish(string $deploymentId, string $event, array $data): void
    {
        try {
            Redis::publish('deployment.'.$deploymentId, json_encode([
                'event' => $event,
                'data' => $data,
            ], JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            // Redis pub/sub is best-effort; Reverb still delivers live updates.
        }
    }
}
