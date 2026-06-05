<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Services;

use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Models\Deployment;

final class RunnerSlotInspector
{
    public function __construct(
        private readonly RunnerSlotManager $slotManager,
    ) {
    }

    /**
     * @return list<array{slot: int, buildId: string, reason: string}>
     */
    public function orphanedSlots(BuildRunner $runner): array
    {
        $orphaned = [];

        foreach ($this->slotManager->activeSlotEntries($runner) as $entry) {
            $deployment = Deployment::query()
                ->withoutGlobalScope('owned_by_organization')
                ->whereKey($entry['buildId'])
                ->first();

            if ($deployment === null) {
                $orphaned[] = [
                    'slot' => $entry['slot'],
                    'buildId' => $entry['buildId'],
                    'reason' => 'deployment_not_found',
                ];

                continue;
            }

            if ($deployment->status !== DeploymentStatus::BUILDING) {
                $orphaned[] = [
                    'slot' => $entry['slot'],
                    'buildId' => $entry['buildId'],
                    'reason' => 'deployment_not_building',
                ];
            }
        }

        return $orphaned;
    }

    public function clearOrphanedSlots(BuildRunner $runner): int
    {
        $cleared = 0;

        foreach ($this->orphanedSlots($runner) as $orphan) {
            $this->slotManager->release($runner, $orphan['slot']);
            $cleared++;
        }

        return $cleared;
    }
}
