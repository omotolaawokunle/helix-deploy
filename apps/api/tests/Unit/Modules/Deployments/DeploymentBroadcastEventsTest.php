<?php

declare(strict_types=1);

use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Events\DeploymentCompleted;
use App\Modules\Deployments\Events\DeploymentRolledBack;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Events\DeploymentLogLine;
use App\Modules\Deployments\Events\DeploymentStepUpdated;
use App\Modules\Deployments\Models\DeploymentStep;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Str;

it('deployment log line broadcasts step-aware payload', function (): void {
    [, , , $deployment] = executionFixture();

    $event = new DeploymentLogLine($deployment, 'Cloning repository...', 'step-uuid');
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($event->broadcastAs())->toBe('deployment.log_line')
        ->and($event->broadcastWith()['stepId'])->toBe('step-uuid')
        ->and($event->broadcastWith()['timestamp'])->not->toBeEmpty();
});

it('deployment step updated maps running status to step.started sse event', function (): void {
    [, , , $deployment] = executionFixture();

    $step = DeploymentStep::query()->create([
        'id' => (string) Str::uuid(),
        'deployment_id' => (string) $deployment->getKey(),
        'name' => 'clone-repository',
        'status' => DeploymentStepStatus::RUNNING,
        'order' => 2,
        'started_at' => now(),
    ]);

    $event = new DeploymentStepUpdated($deployment, $step);

    expect($event->broadcastAs())->toBe('deployment.step.updated')
        ->and($event->sseEventName())->toBe('step.started')
        ->and($event->broadcastWith()['status'])->toBe('running');
});

it('deployment rolled back exposes rollback payload fields', function (): void {
    [, , , $deployment] = executionFixture();
    $deployment->forceFill([
        'type' => DeploymentType::ROLLBACK,
        'status' => DeploymentStatus::SUCCESS,
        'release_path' => '/var/www/app.example.test/releases/target',
        'rollback_target_id' => 'target-deployment-id',
        'started_at' => now()->subMinutes(1),
        'finished_at' => now(),
    ]);

    $rolledBack = new DeploymentRolledBack($deployment, 'release-id');

    expect($rolledBack->broadcastAs())->toBe('deployment.rolled_back')
        ->and($rolledBack->broadcastWith()['status'])->toBe('success')
        ->and($rolledBack->broadcastWith()['releaseId'])->toBe('release-id')
        ->and($rolledBack->broadcastWith()['rollbackTargetId'])->toBe('target-deployment-id')
        ->and($rolledBack->broadcastWith()['releasePath'])->toBe('/var/www/app.example.test/releases/target');
});

it('deployment completed exposes terminal payload fields', function (): void {
    [, , , $deployment] = executionFixture();
    $deployment->forceFill([
        'status' => DeploymentStatus::SUCCESS,
        'commit_hash' => 'abc',
        'started_at' => now()->subMinutes(2),
        'finished_at' => now(),
    ]);

    $completed = new DeploymentCompleted($deployment, 'release-id');

    expect($completed->broadcastAs())->toBe('deployment.completed')
        ->and($completed->broadcastWith()['status'])->toBe('success')
        ->and($completed->broadcastWith()['releaseId'])->toBe('release-id')
        ->and($completed->broadcastWith()['commitHash'])->toBe('abc');
});
