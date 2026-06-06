<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Jobs\StuckDeploymentWatchdogJob;
use App\Modules\Deployments\Models\DeploymentStep;
use Illuminate\Support\Str;

it('marks stuck deployments and running steps as failed', function (): void {
    [, , , $deployment] = executionFixture();
    $deployment->forceFill([
        'status' => DeploymentStatus::RUNNING,
        'started_at' => now()->subMinutes(60),
    ])->save();

    $step = DeploymentStep::query()->create([
        'id' => (string) Str::uuid(),
        'deployment_id' => (string) $deployment->getKey(),
        'name' => 'clone-repository',
        'status' => DeploymentStepStatus::RUNNING,
        'order' => 1,
        'started_at' => now()->subMinutes(30),
    ]);

    (new StuckDeploymentWatchdogJob())->handle();

    $deployment->refresh();
    $step->refresh();

    expect($deployment->status)->toBe(DeploymentStatus::FAILED)
        ->and($step->status)->toBe(DeploymentStepStatus::FAILED)
        ->and($step->output)->toBe('Deployment timed out')
        ->and(AuditLog::query()->where('operation', 'deployment.timed_out')->exists())->toBeTrue();
});
