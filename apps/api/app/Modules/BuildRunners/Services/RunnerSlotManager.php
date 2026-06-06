<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Services;

use App\Modules\BuildRunners\Contracts\RunnerSlotStoreInterface;
use App\Modules\BuildRunners\Events\BuildRunnerSlotsUpdated;
use App\Modules\BuildRunners\Models\BuildRunner;

final class RunnerSlotManager
{
    private const SLOT_TTL_SECONDS = 3600;

    public function __construct(
        private readonly RunnerSlotStoreInterface $store,
    ) {
    }

    public function acquire(BuildRunner $runner, string $buildId): ?int
    {
        $maxConcurrent = max(1, (int) $runner->max_concurrent_builds);

        for ($slot = 0; $slot < $maxConcurrent; $slot++) {
            $key = $this->slotKey((string) $runner->getKey(), $slot);

            if ($this->store->setIfNotExists($key, $buildId, self::SLOT_TTL_SECONDS)) {
                $this->broadcastSlotsUpdated($runner);

                return $slot;
            }
        }

        return null;
    }

    public function release(BuildRunner $runner, int $slot): void
    {
        $this->store->delete($this->slotKey((string) $runner->getKey(), $slot));
        $this->broadcastSlotsUpdated($runner);
    }

    public function releaseByBuildId(BuildRunner $runner, string $buildId): void
    {
        $maxConcurrent = max(1, (int) $runner->max_concurrent_builds);

        for ($slot = 0; $slot < $maxConcurrent; $slot++) {
            $key = $this->slotKey((string) $runner->getKey(), $slot);

            if ($this->store->get($key) === $buildId) {
                $this->store->delete($key);
                $this->broadcastSlotsUpdated($runner);

                return;
            }
        }
    }

    public function activeBuildCount(BuildRunner $runner): int
    {
        $maxConcurrent = max(1, (int) $runner->max_concurrent_builds);
        $active = 0;

        for ($slot = 0; $slot < $maxConcurrent; $slot++) {
            if ($this->store->exists($this->slotKey((string) $runner->getKey(), $slot))) {
                $active++;
            }
        }

        return $active;
    }

    public function availableSlots(BuildRunner $runner): int
    {
        return max(0, max(1, (int) $runner->max_concurrent_builds) - $this->activeBuildCount($runner));
    }

    public function hasActiveSlots(BuildRunner $runner): bool
    {
        return $this->activeBuildCount($runner) > 0;
    }

    /**
     * @return list<array{slot: int, buildId: string}>
     */
    public function activeSlotEntries(BuildRunner $runner): array
    {
        $maxConcurrent = max(1, (int) $runner->max_concurrent_builds);
        $entries = [];

        for ($slot = 0; $slot < $maxConcurrent; $slot++) {
            $key = $this->slotKey((string) $runner->getKey(), $slot);
            $buildId = $this->store->get($key);

            if ($buildId !== null && $buildId !== '') {
                $entries[] = [
                    'slot' => $slot,
                    'buildId' => $buildId,
                ];
            }
        }

        return $entries;
    }

    private function slotKey(string $runnerId, int $slot): string
    {
        return sprintf('runner:%s:slot:%d', $runnerId, $slot);
    }

    private function broadcastSlotsUpdated(BuildRunner $runner): void
    {
        event(new BuildRunnerSlotsUpdated($runner));
    }
}
