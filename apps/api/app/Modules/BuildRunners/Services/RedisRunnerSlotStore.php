<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Services;

use App\Modules\BuildRunners\Contracts\RunnerSlotStoreInterface;
use Illuminate\Support\Facades\Redis;

final class RedisRunnerSlotStore implements RunnerSlotStoreInterface
{
    public function setIfNotExists(string $key, string $value, int $ttlSeconds): bool
    {
        return (bool) Redis::set($key, $value, ['NX', 'EX' => $ttlSeconds]);
    }

    public function get(string $key): ?string
    {
        $value = Redis::get($key);

        return is_string($value) ? $value : null;
    }

    public function delete(string $key): void
    {
        Redis::del($key);
    }

    public function exists(string $key): bool
    {
        return (bool) Redis::exists($key);
    }
}
