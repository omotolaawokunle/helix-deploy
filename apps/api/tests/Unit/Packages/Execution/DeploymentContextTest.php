<?php

declare(strict_types=1);

use App\Modules\Deployments\Models\DeploymentStep as DeploymentStepRecord;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Packages\Execution\DeploymentContext;
use Illuminate\Support\Facades\Event;

it('flushes buffered log lines to deployment step record', function (): void {
    Event::fake();
    [, $server, $site, $deployment] = executionFixture();
    $ssh = fakeSsh();
    $ctx = executionContext($site, $deployment, $server, $ssh);

    $record = DeploymentStepRecord::query()->create([
        'deployment_id' => (string) $deployment->getKey(),
        'name' => 'verify-connection',
        'status' => DeploymentStepStatus::RUNNING,
        'order' => 0,
        'started_at' => now(),
    ]);
    $ctx->currentStepRecord = $record;

    foreach (range(1, 12) as $index) {
        $ctx->log("line {$index}");
    }
    $ctx->flushLog();

    $record->refresh();
    expect($record->output)->toContain('line 1')
        ->and($record->output)->toContain('line 12');
});

it('deployment context paths follow domain layout', function (): void {
    [, $server, $site, $deployment] = executionFixture();
    $ctx = DeploymentContext::forDeployment($deployment, $site, $server, fakeSsh());

    expect($ctx->releasePath)->toBe('/var/www/app.example.test/releases/'.$deployment->getKey())
        ->and($ctx->sharedPath)->toBe('/var/www/app.example.test/shared')
        ->and($ctx->currentPath)->toBe('/var/www/app.example.test/current');
});
