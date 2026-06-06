<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Contracts;

interface RunnerSlotStoreInterface
{
    public function setIfNotExists(string $key, string $value, int $ttlSeconds): bool;

    public function get(string $key): ?string;

    public function delete(string $key): void;

    public function exists(string $key): bool;
}
