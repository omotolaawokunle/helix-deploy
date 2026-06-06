<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Resources;

use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use App\Modules\Projects\Resources\ProjectResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BuildRunner
 */
class BuildRunnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $slotManager = app(RunnerSlotManager::class);
        $activeBuilds = $slotManager->activeBuildCount($this->resource);

        return [
            'id' => (string) $this->getKey(),
            'name' => $this->name,
            'ipAddress' => $this->ip_address,
            'sshPort' => $this->ssh_port,
            'sshUser' => $this->ssh_user,
            'status' => $this->status?->value,
            'maxConcurrentBuilds' => $this->max_concurrent_builds,
            'activeBuilds' => $activeBuilds,
            'availableSlots' => max(0, (int) $this->max_concurrent_builds - $activeBuilds),
            'cpuCores' => $this->cpu_cores,
            'ramGb' => $this->ram_gb,
            'supportedRuntimes' => $this->supported_runtimes ?? [],
            'project' => $this->whenLoaded('project', fn () => $this->project === null ? null : ProjectResource::make($this->project)),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
