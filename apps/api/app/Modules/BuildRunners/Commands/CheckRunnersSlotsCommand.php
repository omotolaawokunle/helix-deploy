<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Commands;

use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Services\RunnerSlotInspector;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use Illuminate\Console\Command;

final class CheckRunnersSlotsCommand extends Command
{
    protected $signature = 'runners:check-slots
                            {--org= : Limit to an organization UUID}
                            {--runner= : Limit to a single build runner UUID}
                            {--fix : Clear orphaned slots only}';

    protected $description = 'Inspect build runner slot usage and optionally clear orphaned slots.';

    public function handle(RunnerSlotManager $slotManager, RunnerSlotInspector $slotInspector): int
    {
        $runners = $this->resolveRunners();
        $fix = (bool) $this->option('fix');
        $totalOrphans = 0;
        $totalCleared = 0;

        if ($runners->isEmpty()) {
            $this->warn('No build runners matched the provided filters.');

            return self::SUCCESS;
        }

        foreach ($runners as $runner) {
            $active = $slotManager->activeSlotEntries($runner);
            $orphans = $slotInspector->orphanedSlots($runner);

            $this->line(sprintf(
                'Runner %s (%s): %d active slot(s), %d orphaned',
                (string) $runner->name,
                (string) $runner->getKey(),
                count($active),
                count($orphans),
            ));

            foreach ($active as $entry) {
                $this->line(sprintf('  slot %d -> deployment %s', $entry['slot'], $entry['buildId']));
            }

            foreach ($orphans as $orphan) {
                $this->warn(sprintf(
                    '  orphaned slot %d -> deployment %s (%s)',
                    $orphan['slot'],
                    $orphan['buildId'],
                    $orphan['reason'],
                ));
            }

            $totalOrphans += count($orphans);

            if ($fix && $orphans !== []) {
                $cleared = $slotInspector->clearOrphanedSlots($runner);
                $totalCleared += $cleared;
                $this->info(sprintf('  cleared %d orphaned slot(s)', $cleared));
            }
        }

        if ($fix) {
            $this->info(sprintf('Cleared %d orphaned slot(s) total.', $totalCleared));
        } else {
            $this->info(sprintf('Found %d orphaned slot(s). Re-run with --fix to clear them.', $totalOrphans));
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, BuildRunner>
     */
    private function resolveRunners(): \Illuminate\Support\Collection
    {
        $query = BuildRunner::query()->withoutGlobalScope('owned_by_organization');

        $organizationId = $this->option('org');
        if (is_string($organizationId) && $organizationId !== '') {
            $query->where('organization_id', $organizationId);
        }

        $runnerId = $this->option('runner');
        if (is_string($runnerId) && $runnerId !== '') {
            $query->whereKey($runnerId);
        }

        return $query->orderBy('name')->get();
    }
}
