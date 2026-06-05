<?php

declare(strict_types=1);

namespace App\Packages\Realtime;

use Illuminate\Support\Facades\Redis;

final class CommandStreamPublisher
{
    /**
     * @param array<string, mixed> $data
     */
    public function publish(string $commandId, string $event, array $data): void
    {
        try {
            Redis::publish('command.'.$commandId, json_encode([
                'event' => $event,
                'data' => $data,
            ], JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            // Redis pub/sub is best-effort for SSE streaming.
        }
    }
}
