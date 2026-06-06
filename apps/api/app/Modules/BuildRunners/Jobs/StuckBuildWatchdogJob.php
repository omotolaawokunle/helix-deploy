<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Jobs;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentStepPhase;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Events\DeploymentCompleted;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\DeploymentStep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StuckBuildWatchdogJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('monitoring');
    }

    public function handle(RunnerSlotManager $slotManager): void
    {
        $thresholdMinutes = (int) config('helixdeploy.build_timeout_minutes', 30);
        $cutoff = now()->subMinutes($thresholdMinutes);

        $stuckDeployments = Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->with('buildRunner')
            ->where('status', DeploymentStatus::BUILDING->value)
            ->where('started_at', '<', $cutoff)
            ->get();

        foreach ($stuckDeployments as $deployment) {
            $beforeState = [
                'deploymentId' => (string) $deployment->getKey(),
                'status' => $deployment->status->value,
                'startedAt' => $deployment->started_at?->toIso8601String(),
                'buildRunnerId' => $deployment->build_runner_id,
            ];

            $runner = $deployment->buildRunner;
            if ($runner instanceof BuildRunner) {
                $slotManager->releaseByBuildId($runner, (string) $deployment->getKey());
            }

            $deployment->forceFill([
                'status' => DeploymentStatus::FAILED,
                'finished_at' => now(),
            ])->save();

            DeploymentStep::query()
                ->where('deployment_id', (string) $deployment->getKey())
                ->where('phase', DeploymentStepPhase::BUILD->value)
                ->where('status', DeploymentStepStatus::RUNNING->value)
                ->update([
                    'status' => DeploymentStepStatus::FAILED->value,
                    'finished_at' => now(),
                    'output' => 'Build timed out',
                ]);

            AuditLog::record(
                operation: 'deployment.build_timed_out',
                resource: $deployment->refresh(),
                beforeState: $beforeState,
                afterState: [
                    'status' => DeploymentStatus::FAILED->value,
                    'thresholdMinutes' => $thresholdMinutes,
                ],
            );

            event(new DeploymentCompleted($deployment));
        }
    }
}
