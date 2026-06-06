<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Services;

use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class BuildRunnerPool
{
    public function __construct(
        private readonly RunnerSlotManager $slotManager,
    ) {
    }

    public function acquire(Site $site, Organization $org): ?BuildRunner
    {
        $candidates = $this->candidateRunners($site, $org);

        if ($candidates->isEmpty()) {
            return null;
        }

        $preferredRunnerId = $site->build_runner_id;

        if (is_string($preferredRunnerId) && $preferredRunnerId !== '') {
            $preferred = $candidates->firstWhere('id', $preferredRunnerId);

            if ($preferred instanceof BuildRunner && $this->slotManager->availableSlots($preferred) > 0) {
                return $preferred;
            }
        }

        return $candidates
            ->sortByDesc(fn (BuildRunner $runner): int => $this->slotManager->availableSlots($runner))
            ->first(fn (BuildRunner $runner): bool => $this->slotManager->availableSlots($runner) > 0);
    }

    /**
     * @return Collection<int, BuildRunner>
     */
    public function candidateRunners(Site $site, Organization $org): Collection
    {
        $projectId = $site->project_id;

        return BuildRunner::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $org->getKey())
            ->where('status', BuildRunnerStatus::ONLINE->value)
            ->where(function (Builder $query) use ($projectId): void {
                $query->whereNull('project_id');

                if (is_string($projectId) && $projectId !== '') {
                    $query->orWhere('project_id', $projectId);
                }
            })
            ->get()
            ->filter(fn (BuildRunner $runner): bool => $runner->supportsRuntime($site->runtime))
            ->values();
    }
}
