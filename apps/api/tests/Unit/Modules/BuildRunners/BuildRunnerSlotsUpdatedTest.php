<?php

declare(strict_types=1);

use App\Modules\BuildRunners\Events\BuildRunnerSlotsUpdated;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    useInMemoryRunnerSlotStore();
});

it('broadcasts slot updates when a slot is acquired', function (): void {
    Event::fake([BuildRunnerSlotsUpdated::class]);

    $runner = createPoolTestRunner(maxConcurrentBuilds: 2);
    $slotManager = app(RunnerSlotManager::class);

    $slotManager->acquire($runner, 'build-a');

    Event::assertDispatched(BuildRunnerSlotsUpdated::class, function (BuildRunnerSlotsUpdated $event) use ($runner): bool {
        return (string) $event->runner->getKey() === (string) $runner->getKey();
    });
});

it('broadcasts slot updates when a slot is released', function (): void {
    Event::fake([BuildRunnerSlotsUpdated::class]);

    $runner = createPoolTestRunner(maxConcurrentBuilds: 1);
    $slotManager = app(RunnerSlotManager::class);

    $slotManager->acquire($runner, 'build-a');
    Event::fake([BuildRunnerSlotsUpdated::class]);

    $slotManager->release($runner, 0);

    Event::assertDispatched(BuildRunnerSlotsUpdated::class);
});

it('broadcasts slot updates when releasing by build id', function (): void {
    Event::fake([BuildRunnerSlotsUpdated::class]);

    $runner = createPoolTestRunner(maxConcurrentBuilds: 2);
    $slotManager = app(RunnerSlotManager::class);

    $slotManager->acquire($runner, 'build-a');
    Event::fake([BuildRunnerSlotsUpdated::class]);

    $slotManager->releaseByBuildId($runner, 'build-a');

    Event::assertDispatched(BuildRunnerSlotsUpdated::class);
});

it('includes slot counts in the broadcast payload', function (): void {
    $runner = createPoolTestRunner(maxConcurrentBuilds: 2);
    $slotManager = app(RunnerSlotManager::class);

    $slotManager->acquire($runner, 'build-a');

    $event = new BuildRunnerSlotsUpdated($runner);
    $payload = $event->broadcastWith();

    expect($payload)->toMatchArray([
        'runnerId' => (string) $runner->getKey(),
        'activeBuilds' => 1,
        'maxConcurrentBuilds' => 2,
        'availableSlots' => 1,
    ]);
});
