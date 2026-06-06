<?php

declare(strict_types=1);

use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Services\RunnerSlotInspector;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use App\Modules\Deployments\Enums\DeploymentStatus;
use Illuminate\Support\Str;

beforeEach(function (): void {
    useInMemoryRunnerSlotStore();
});

it('detects orphaned slots for missing and non-building deployments', function (): void {
    [$organization, , , $deployment] = executionFixture();
    $runner = createInspectorTestRunner($organization, (string) $deployment->triggered_by);
    $slotManager = app(RunnerSlotManager::class);
    $inspector = app(RunnerSlotInspector::class);

    $slotManager->acquire($runner, (string) Str::uuid());
    $slotManager->acquire($runner, (string) $deployment->getKey());

    $deployment->forceFill(['status' => DeploymentStatus::BUILT])->save();

    $orphans = $inspector->orphanedSlots($runner);

    expect($orphans)->toHaveCount(2)
        ->and(collect($orphans)->pluck('reason')->all())->toContain('deployment_not_found', 'deployment_not_building');
});

it('clears only orphaned slots when fix is requested', function (): void {
    [$organization, , , $deployment] = executionFixture();
    $runner = createInspectorTestRunner($organization, (string) $deployment->triggered_by);
    $slotManager = app(RunnerSlotManager::class);
    $inspector = app(RunnerSlotInspector::class);

    $slotManager->acquire($runner, (string) Str::uuid());
    $slotManager->acquire($runner, (string) $deployment->getKey());

    $deployment->forceFill([
        'status' => DeploymentStatus::BUILDING,
        'started_at' => now(),
    ])->save();

    $cleared = $inspector->clearOrphanedSlots($runner);

    expect($cleared)->toBe(1)
        ->and($slotManager->activeBuildCount($runner))->toBe(1)
        ->and($slotManager->activeSlotEntries($runner)[0]['buildId'])->toBe((string) $deployment->getKey());
});

function createInspectorTestRunner(
    \App\Modules\Organizations\Models\Organization $organization,
    string $ownerId,
): BuildRunner {
    return BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Inspector Runner',
        'ip_address' => '10.0.0.95',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => 2,
        'supported_runtimes' => ['php'],
        'created_by' => $ownerId,
    ]);
}
