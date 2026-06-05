<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Models\DeploymentStep as DeploymentStepRecord;
use App\Packages\Execution\BuildContext;
use App\Packages\SSH\FakeSSHConnection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('flushes buffered build log lines to deployment step record', function (): void {
    Event::fake();
    [, $server, $site, $deployment] = executionFixture();
    $runner = createBuildContextRunner($deployment);
    $ssh = fakeSsh();
    $ctx = BuildContext::forDeployment($deployment, $site, $runner, $ssh);

    $record = DeploymentStepRecord::query()->create([
        'deployment_id' => (string) $deployment->getKey(),
        'name' => 'Build: clone-repository',
        'status' => DeploymentStepStatus::RUNNING,
        'order' => 0,
        'started_at' => now(),
    ]);
    $ctx->currentStepRecord = $record;

    foreach (range(1, 12) as $index) {
        $ctx->log("build line {$index}");
    }
    $ctx->flushLog();

    $record->refresh();
    expect($record->output)->toContain('build line 1')
        ->and($record->output)->toContain('build line 12');
});

it('runs commands on the build runner ssh connection', function (): void {
    [, , $site, $deployment] = executionFixture();
    $runner = createBuildContextRunner($deployment);
    $ssh = (new FakeSSHConnection())->connect();
    $ssh->addSequence('*', sshSuccess('runner-ok'));

    $ctx = BuildContext::forDeployment($deployment, $site, $runner, $ssh);
    $result = $ctx->run('echo runner-ok');

    expect($result->stdout)->toBe('runner-ok')
        ->and($ssh->getExecutedCommands())->toHaveCount(1);
});

it('build context paths follow deployment layout on the runner', function (): void {
    [, , $site, $deployment] = executionFixture();
    $runner = createBuildContextRunner($deployment);
    $ctx = BuildContext::forDeployment($deployment, $site, $runner, fakeSsh());

    expect($ctx->buildPath)->toBe('/builds/'.$deployment->getKey().'/')
        ->and($ctx->artifactPath)->toBe('/tmp/'.$deployment->getKey().'.tar.gz')
        ->and($ctx->runner->getKey())->toBe($runner->getKey());
});

function createBuildContextRunner(\App\Modules\Deployments\Models\Deployment $deployment): BuildRunner
{
    $owner = User::query()->findOrFail($deployment->triggered_by);

    return BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $deployment->organization_id,
        'name' => 'context-build-'.Str::random(4),
        'ip_address' => '10.0.0.70',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => 1,
        'supported_runtimes' => ['php'],
        'created_by' => (string) $owner->getKey(),
    ]);
}
