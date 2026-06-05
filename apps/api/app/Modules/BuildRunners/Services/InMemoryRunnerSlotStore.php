<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Services;

use App\Modules\BuildRunners\Contracts\RunnerSlotStoreInterface;

final class InMemoryRunnerSlotStore implements RunnerSlotStoreInterface
{
    /** @var array<string, string> */
    private array $values = [];

    public function setIfNotExists(string $key, string $value, int $ttlSeconds): bool
    {
        if (array_key_exists($key, $this->values)) {
            return false;
        }

        $this->values[$key] = $value;

        return true;
    }

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function delete(string $key): void
    {
        unset($this->values[$key]);
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function flush(): void
    {
        $this->values = [];
    }
}
