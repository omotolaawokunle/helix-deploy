<?php

declare(strict_types=1);

use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Enums\BuildStrategy;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentStepPhase;
use App\Modules\Deployments\Exceptions\ConcurrentDeploymentException;
use App\Modules\Deployments\Jobs\RunBuildJob;
use App\Modules\Deployments\Jobs\RunDeploymentJob;
use App\Modules\Deployments\Models\DeploymentStep;
use App\Modules\Sites\Enums\Runtime;
use App\Packages\Execution\PipelineBuilder;
use App\Packages\SSH\BuildRunnerSSHManager;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    useInMemoryRunnerSlotStore();
});

it('returns empty build steps for on_server strategy', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::PHP);

    $plan = (new PipelineBuilder())->buildPlan($site, $deployment);

    expect($plan->buildSteps)->toBe([])
        ->and($plan->usesRunnerBuild())->toBeFalse()
        ->and($plan->deploySteps)->not->toBeEmpty();
});

it('builds separate build and deploy step lists for runner strategy', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::PHP);
    $site->forceFill(['build_strategy' => BuildStrategy::RUNNER->value])->save();
    $deployment->forceFill(['build_strategy' => BuildStrategy::RUNNER->value])->save();

    $plan = (new PipelineBuilder())->buildPlan($site, $deployment);

    $buildNames = array_map(static fn ($step): string => $step->name(), $plan->buildSteps);
    $deployNames = array_map(static fn ($step): string => $step->name(), $plan->deploySteps);

    expect($plan->usesRunnerBuild())->toBeTrue()
        ->and($buildNames)->toContain('create-artifact', 'transfer-artifact', 'clone-repository')
        ->and($deployNames)->toContain('extract-artifact', 'activate-release')
        ->and($deployNames)->not->toContain('clone-repository');
});

it('completes runner build phase and dispatches deployment job', function (): void {
    Bus::fake([RunDeploymentJob::class]);
    Event::fake();

    [$organization, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $site->forceFill([
        'build_strategy' => BuildStrategy::RUNNER->value,
        'run_migrations' => false,
    ])->save();

    $runner = createRunnerForBuildJob($organization, $deployment->triggered_by);
    attachServerCredential($server, $deployment->triggered_by);

    $deployment->forceFill([
        'status' => DeploymentStatus::PENDING,
        'build_strategy' => BuildStrategy::RUNNER->value,
        'build_runner_id' => (string) $runner->getKey(),
    ])->save();

    $runnerFake = stubRunnerBuildSsh((string) $deployment->getKey());
    $targetFake = stubRunnerTargetSsh($site->domain, (string) $deployment->getKey());

    $this->mock(BuildRunnerSSHManager::class, function ($mock) use ($runnerFake): void {
        $mock->shouldReceive('connect')->once()->andReturn($runnerFake);
    });
    $this->mock(SSHManager::class, function ($mock) use ($targetFake): void {
        $mock->shouldReceive('connect')->once()->andReturn($targetFake);
    });

    $job = new RunBuildJob((string) $deployment->getKey(), (string) $deployment->triggered_by);
    invokeRunBuildJob($job);

    $deployment->refresh();

    expect($deployment->status)->toBe(DeploymentStatus::BUILT)
        ->and($deployment->build_artifact_id)->not->toBeNull()
        ->and(DeploymentStep::query()->where('deployment_id', $deployment->getKey())->where('phase', DeploymentStepPhase::BUILD->value)->count())
        ->toBeGreaterThan(0);

    Bus::assertDispatched(RunDeploymentJob::class);
});

it('marks build failed and does not dispatch deployment job when a build step fails', function (): void {
    Bus::fake([RunDeploymentJob::class]);

    [$organization, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $site->forceFill(['build_strategy' => BuildStrategy::RUNNER->value])->save();

    $runner = createRunnerForBuildJob($organization, $deployment->triggered_by);
    attachServerCredential($server, $deployment->triggered_by);

    $deployment->forceFill([
        'status' => DeploymentStatus::PENDING,
        'build_strategy' => BuildStrategy::RUNNER->value,
        'build_runner_id' => (string) $runner->getKey(),
    ])->save();

    $runnerFake = (new FakeSSHConnection())->connect();
    $runnerFake->addSequence('echo "_helix_runner_ok_"*', sshSuccess('_helix_runner_ok_'));
    $runnerFake->addSequence('mkdir -p *', sshSuccess());
    $runnerFake->addSequence('git clone *', sshFailure('clone failed'));

    $targetFake = (new FakeSSHConnection())->connect();

    $this->mock(BuildRunnerSSHManager::class, function ($mock) use ($runnerFake): void {
        $mock->shouldReceive('connect')->once()->andReturn($runnerFake);
    });
    $this->mock(SSHManager::class, function ($mock) use ($targetFake): void {
        $mock->shouldReceive('connect')->once()->andReturn($targetFake);
    });

    $job = new RunBuildJob((string) $deployment->getKey(), (string) $deployment->triggered_by);
    invokeRunBuildJob($job);

    $deployment->refresh();

    expect($deployment->status)->toBe(DeploymentStatus::FAILED);
    Bus::assertNotDispatched(RunDeploymentJob::class);
});

it('throws conflict when triggering while a build is in progress', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::PHP);
    $deployment->forceFill(['status' => DeploymentStatus::BUILDING])->save();

    $actor = \App\Models\User::query()->findOrFail($deployment->triggered_by);
    $action = app(\App\Modules\Deployments\Actions\TriggerDeploymentAction::class);

    expect(fn () => $action->execute($site, $actor, new \App\Modules\Deployments\DTOs\TriggerDeploymentDTO()))
        ->toThrow(ConcurrentDeploymentException::class);
});

it('run build and deployment jobs share the same unique id for a site', function (): void {
    [, , , $deployment] = executionFixture(Runtime::PHP);

    $buildJob = new RunBuildJob((string) $deployment->getKey(), (string) $deployment->triggered_by);
    $deployJob = new RunDeploymentJob((string) $deployment->getKey(), (string) $deployment->triggered_by);

    expect($buildJob->uniqueId())->toBe('site_'.$deployment->site_id)
        ->and($deployJob->uniqueId())->toBe('site_'.$deployment->site_id);
});

function invokeRunBuildJob(RunBuildJob $job): void
{
    $job->handle(
        new PipelineBuilder(),
        new \App\Packages\Execution\BuildStepRunner(),
        app(BuildRunnerSSHManager::class),
        app(SSHManager::class),
        app(\App\Modules\Credentials\CredentialVault::class),
        app(\App\Modules\Sites\Services\Git\AuthenticatedGitCloneUrlResolver::class),
        app(\App\Modules\Deployments\Services\DeploymentCancellationService::class),
        app(\App\Modules\BuildRunners\Services\RunnerSlotManager::class),
    );
}

function createRunnerForBuildJob(\App\Models\Organization $organization, string $ownerId): BuildRunner
{
    $credential = Credential::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'ssh_private_key',
        'name' => 'Runner Key',
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => 'fp',
        'created_by' => $ownerId,
        'last_used_at' => null,
    ]);

    return BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Build Runner',
        'ip_address' => '10.0.0.80',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => 2,
        'supported_runtimes' => ['php'],
        'credential_id' => (string) $credential->getKey(),
        'created_by' => $ownerId,
    ]);
}

function attachServerCredential(\App\Modules\Servers\Models\Server $server, string $ownerId): void
{
    $credential = Credential::query()->create([
        'organization_id' => (string) $server->organization_id,
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'ssh_private_key',
        'name' => 'Server Key',
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => 'fp',
        'created_by' => $ownerId,
        'last_used_at' => null,
    ]);

    $server->forceFill(['credential_id' => (string) $credential->getKey()])->save();
}

function stubRunnerBuildSsh(string $deploymentId): FakeSSHConnection
{
    $fake = (new FakeSSHConnection())->connect();
    $buildPath = '/builds/'.$deploymentId.'/';
    $artifactPath = '/tmp/'.$deploymentId.'.tar.gz';
    $success = static fn (): SSHResult => sshSuccess();

    $fake->addSequence('echo "_helix_runner_ok_"*', sshSuccess('_helix_runner_ok_ uname'));
    $fake->addSequence('mkdir -p *', $success());
    $fake->addSequence('git clone *', $success());
    $fake->addSequence('git -C * rev-parse HEAD', sshSuccess('deadbeef'));
    $fake->addSequence('git -C * log -1 *', sshSuccess('Build commit'));
    $fake->addSequence('*composer install*', $success());
    $fake->addSequence('test -f *', sshFailure());
    $fake->addSequence('test -f *', sshFailure());
    $fake->addSequence('test -f *', $success());
    $fake->addSequence(sprintf('tar -czf %s -C %s . --exclude=*', escapeshellarg($artifactPath), escapeshellarg($buildPath)), $success());
    $fake->addSequence(sprintf('sha256sum %s', escapeshellarg($artifactPath)), sshSuccess('abc123  '.$artifactPath));
    $fake->addSequence(sprintf('stat -c%%s %s', escapeshellarg($artifactPath)), sshSuccess('4096'));
    $fake->addSequence('scp -o StrictHostKeyChecking=no*', $success());
    $fake->addSequence(sprintf('rm -f %s', escapeshellarg($artifactPath)), $success());
    $fake->addSequence('rm -rf *', $success());

    return $fake;
}

function stubRunnerTargetSsh(string $domain, string $deploymentId): FakeSSHConnection
{
    $fake = (new FakeSSHConnection())->connect();
    $artifactPath = '/tmp/'.$deploymentId.'.tar.gz';
    $releasePath = '/var/www/'.$domain.'/releases/'.$deploymentId;
    $success = static fn (): SSHResult => sshSuccess();

    $fake->addSequence(sprintf('sha256sum %s', escapeshellarg($artifactPath)), sshSuccess('abc123  '.$artifactPath));

    return $fake;
}
