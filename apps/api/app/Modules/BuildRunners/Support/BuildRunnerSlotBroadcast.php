<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Support;

use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Services\RunnerSlotManager;

final class BuildRunnerSlotBroadcast
{
    /**
     * @return array{
     *     runnerId: string,
     *     activeBuilds: int,
     *     maxConcurrentBuilds: int,
     *     availableSlots: int
     * }
     */
    public static function payload(BuildRunner $runner, RunnerSlotManager $slotManager): array
    {
        $maxConcurrentBuilds = max(1, (int) $runner->max_concurrent_builds);
        $activeBuilds = $slotManager->activeBuildCount($runner);

        return [
            'runnerId' => (string) $runner->getKey(),
            'activeBuilds' => $activeBuilds,
            'maxConcurrentBuilds' => $maxConcurrentBuilds,
            'availableSlots' => max(0, $maxConcurrentBuilds - $activeBuilds),
        ];
    }
}
