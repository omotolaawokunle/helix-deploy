<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Jobs;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Events\DeploymentCompleted;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\DeploymentStep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StuckDeploymentWatchdogJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $thresholdMinutes = (int) config('helixdeploy.stuck_job_threshold_minutes', 35);
        $cutoff = now()->subMinutes($thresholdMinutes);

        $stuckDeployments = Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('status', DeploymentStatus::RUNNING->value)
            ->where('started_at', '<', $cutoff)
            ->get();

        foreach ($stuckDeployments as $deployment) {
            $beforeState = [
                'deploymentId' => (string) $deployment->getKey(),
                'status' => $deployment->status->value,
                'startedAt' => $deployment->started_at?->toIso8601String(),
            ];

            $deployment->forceFill([
                'status' => DeploymentStatus::FAILED,
                'finished_at' => now(),
            ])->save();

            DeploymentStep::query()
                ->where('deployment_id', (string) $deployment->getKey())
                ->where('status', DeploymentStepStatus::RUNNING->value)
                ->update([
                    'status' => DeploymentStepStatus::FAILED->value,
                    'finished_at' => now(),
                    'output' => 'Deployment timed out',
                ]);

            AuditLog::record(
                operation: 'deployment.timed_out',
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
