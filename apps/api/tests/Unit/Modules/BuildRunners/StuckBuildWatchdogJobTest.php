<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Jobs\StuckBuildWatchdogJob;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentStepPhase;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Events\DeploymentCompleted;
use App\Modules\Deployments\Models\DeploymentStep;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    useInMemoryRunnerSlotStore();
});

it('marks stuck building deployments as failed and releases runner slots', function (): void {
    Event::fake([DeploymentCompleted::class]);

    [$organization, , , $deployment] = executionFixture();
    $runner = createWatchdogTestRunner($organization, (string) $deployment->triggered_by);

    $slotManager = app(RunnerSlotManager::class);
    $slotManager->acquire($runner, (string) $deployment->getKey());

    $deployment->forceFill([
        'status' => DeploymentStatus::BUILDING,
        'build_runner_id' => (string) $runner->getKey(),
        'started_at' => now()->subMinutes(60),
    ])->save();

    $step = DeploymentStep::query()->create([
        'id' => (string) Str::uuid(),
        'deployment_id' => (string) $deployment->getKey(),
        'name' => 'clone-repository',
        'phase' => DeploymentStepPhase::BUILD->value,
        'status' => DeploymentStepStatus::RUNNING,
        'order' => 1,
        'started_at' => now()->subMinutes(30),
    ]);

    (new StuckBuildWatchdogJob())->handle($slotManager);

    $deployment->refresh();
    $step->refresh();
    $runner->refresh();

    expect($deployment->status)->toBe(DeploymentStatus::FAILED)
        ->and($deployment->finished_at)->not->toBeNull()
        ->and($step->status)->toBe(DeploymentStepStatus::FAILED)
        ->and($step->output)->toBe('Build timed out')
        ->and($slotManager->activeBuildCount($runner))->toBe(0)
        ->and(AuditLog::query()->where('operation', 'deployment.build_timed_out')->exists())->toBeTrue();

    Event::assertDispatched(DeploymentCompleted::class);
});

function createWatchdogTestRunner(
    \App\Modules\Organizations\Models\Organization $organization,
    string $ownerId,
): BuildRunner {
    return BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Watchdog Runner',
        'ip_address' => '10.0.0.90',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => 2,
        'supported_runtimes' => ['php'],
        'created_by' => $ownerId,
    ]);
}
