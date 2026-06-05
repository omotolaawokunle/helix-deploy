<?php

declare(strict_types=1);

namespace App\Modules\Commands\Services;

use Illuminate\Support\Facades\Cache;

final class CommandCancellationService
{
    private const TTL_SECONDS = 3600;

    public function request(string $commandId): void
    {
        Cache::put($this->key($commandId), true, self::TTL_SECONDS);
    }

    public function isRequested(string $commandId): bool
    {
        return Cache::get($this->key($commandId), false) === true;
    }

    public function clear(string $commandId): void
    {
        Cache::forget($this->key($commandId));
    }

    private function key(string $commandId): string
    {
        return 'command:cancel:'.$commandId;
    }
}
