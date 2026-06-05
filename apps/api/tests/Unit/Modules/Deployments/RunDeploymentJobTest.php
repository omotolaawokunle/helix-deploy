<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\Actions\TriggerDeploymentAction;
use App\Modules\Deployments\DTOs\TriggerDeploymentDTO;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Events\DeploymentCompleted;
use App\Modules\Deployments\Exceptions\ConcurrentDeploymentException;
use App\Modules\Deployments\Jobs\RunDeploymentJob;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\DeploymentStep;
use App\Modules\Deployments\Models\Release;
use App\Modules\Sites\Enums\Runtime;
use App\Packages\Execution\DeploymentRunner;
use App\Packages\Execution\PipelineBuilder;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\Event;

it('runs full php deployment pipeline with ordered steps and release activation', function (): void {
    Event::fake([DeploymentCompleted::class]);

    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $site->forceFill(['run_migrations' => false])->save();

    $credential = \App\Modules\Credentials\Models\Credential::query()->create([
        'organization_id' => (string) $site->organization_id,
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'ssh_private_key',
        'name' => 'Deploy Key',
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => 'fp',
        'created_by' => $deployment->triggered_by,
        'last_used_at' => null,
    ]);
    $server->forceFill(['credential_id' => (string) $credential->getKey()])->save();

    $deployment->forceFill(['status' => DeploymentStatus::PENDING])->save();

    $fake = stubDeploymentSsh($site->domain, (string) $deployment->getKey());

    $this->mock(SSHManager::class, function ($mock) use ($server, $fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    $job = new RunDeploymentJob((string) $deployment->getKey(), (string) $deployment->triggered_by);
    invokeRunDeploymentJob($job);

    $deployment->refresh();

    $pipeline = (new PipelineBuilder())->build($site, $deployment);
    expect(DeploymentStep::query()->where('deployment_id', $deployment->getKey())->count())
        ->toBe(count($pipeline));

    $steps = DeploymentStep::query()
        ->where('deployment_id', $deployment->getKey())
        ->orderBy('order')
        ->get();

    expect($steps->first()?->status)->toBe(DeploymentStepStatus::SUCCESS)
        ->and($deployment->status)->toBe(DeploymentStatus::SUCCESS)
        ->and($deployment->commit_hash)->toBe('deadbeef');

    $activeRelease = Release::query()
        ->where('deployment_id', $deployment->getKey())
        ->where('is_active', true)
        ->first();

    expect($activeRelease)->not->toBeNull();

    expect(AuditLog::query()->where('operation', 'deployment.started')->exists())->toBeTrue();
    expect(AuditLog::query()->where('operation', 'deployment.completed')->exists())->toBeTrue();
    expect(AuditLog::query()->where('operation', 'env_vars.synced')->exists())->toBeTrue();

    $commands = $fake->getExecutedCommands();
    $composerIndex = findCommandIndex($commands, '*composer install*');
    $symlinkIndex = findCommandIndex($commands, 'ln -sfn *');

    expect($composerIndex)->toBeGreaterThan(-1)
        ->and($symlinkIndex)->toBeGreaterThan($composerIndex);

    Event::assertDispatched(DeploymentCompleted::class);
});

it('runs full static deployment pipeline with nginx reload', function (): void {
    Event::fake([DeploymentCompleted::class]);

    [, $server, $site, $deployment] = executionFixture(Runtime::STATIC);

    $credential = \App\Modules\Credentials\Models\Credential::query()->create([
        'organization_id' => (string) $site->organization_id,
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'ssh_private_key',
        'name' => 'Static Deploy Key',
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => 'fp',
        'created_by' => $deployment->triggered_by,
        'last_used_at' => null,
    ]);
    $server->forceFill(['credential_id' => (string) $credential->getKey()])->save();
    $deployment->forceFill(['status' => DeploymentStatus::PENDING])->save();

    $fake = stubStaticDeploymentSsh($site->domain, (string) $deployment->getKey());

    $this->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    $job = new RunDeploymentJob((string) $deployment->getKey(), (string) $deployment->triggered_by);
    invokeRunDeploymentJob($job);

    $deployment->refresh();

    expect($deployment->status)->toBe(DeploymentStatus::SUCCESS);

    $fake->assertCommandExecuted('sudo nginx -t');
    $fake->assertCommandExecuted('sudo systemctl reload nginx');

    Event::assertDispatched(DeploymentCompleted::class);
});

it('leaves later steps pending and skips symlink when a step fails', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $credential = \App\Modules\Credentials\Models\Credential::query()->create([
        'organization_id' => (string) $site->organization_id,
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'ssh_private_key',
        'name' => 'Deploy Key',
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => 'fp',
        'created_by' => $deployment->triggered_by,
        'last_used_at' => null,
    ]);
    $server->forceFill(['credential_id' => (string) $credential->getKey()])->save();

    $deployment->forceFill(['status' => DeploymentStatus::PENDING])->save();

    $fake = (new FakeSSHConnection())->connect();
    $fake->addSequence('echo "_ok_"', sshSuccess('_ok_'));
    $fake->addSequence('df -h / | awk *', sshSuccess('10G'));
    $fake->addSequence('mkdir -p *', sshSuccess(), sshSuccess());
    $fake->addSequence('git clone *', sshSuccess());
    $fake->addSequence('git -C * rev-parse HEAD', sshSuccess('deadbeef'));
    $fake->addSequence('git -C * log -1 *', sshSuccess('Deploy commit'));
    $fake->addSequence('*composer install*', sshFailure('composer failed'));
    $fake->addSequence('rm -rf *', sshSuccess(), sshSuccess(), sshSuccess());

    $this->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    $job = new RunDeploymentJob((string) $deployment->getKey(), (string) $deployment->triggered_by);
    invokeRunDeploymentJob($job);

    $deployment->refresh();
    expect($deployment->status)->toBe(DeploymentStatus::FAILED);

    $failedStep = DeploymentStep::query()
        ->where('deployment_id', $deployment->getKey())
        ->where('name', 'install-composer-deps')
        ->first();

    expect($failedStep?->status)->toBe(DeploymentStepStatus::FAILED);

    $pendingAfterFailure = DeploymentStep::query()
        ->where('deployment_id', $deployment->getKey())
        ->where('status', DeploymentStepStatus::PENDING->value)
        ->where('order', '>', $failedStep?->order ?? 0)
        ->count();

    expect($pendingAfterFailure)->toBeGreaterThan(0);
    $fake->assertCommandNotExecuted('ln -sfn *');
});

it('returns early when deployment is not pending', function (): void {
    [, , , $deployment] = executionFixture(Runtime::PHP);
    $deployment->forceFill(['status' => DeploymentStatus::CANCELLED])->save();

    $this->mock(SSHManager::class, function ($mock): void {
        $mock->shouldNotReceive('connect');
    });

    $job = new RunDeploymentJob((string) $deployment->getKey(), (string) $deployment->triggered_by);
    invokeRunDeploymentJob($job);

    expect(DeploymentStep::query()->where('deployment_id', $deployment->getKey())->count())->toBe(0);
});

function invokeRunDeploymentJob(RunDeploymentJob $job): void
{
    $job->handle(
        new PipelineBuilder(),
        new DeploymentRunner(),
        app(SSHManager::class),
        app(\App\Modules\Credentials\CredentialVault::class),
        app(\App\Modules\Sites\Services\Git\AuthenticatedGitCloneUrlResolver::class),
        app(\App\Modules\Deployments\Services\DeploymentCancellationService::class),
    );
}

it('throws conflict when triggering a second deployment for the same site', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::PHP);
    $deployment->forceFill(['status' => DeploymentStatus::RUNNING])->save();

    $actor = \App\Models\User::query()->findOrFail($deployment->triggered_by);
    $action = app(TriggerDeploymentAction::class);

    expect(fn () => $action->execute($site, $actor, new TriggerDeploymentDTO()))
        ->toThrow(ConcurrentDeploymentException::class);
});

/**
 * @param array<string> $commands
 */
function findCommandIndex(array $commands, string $pattern): int
{
    foreach ($commands as $index => $command) {
        if (fnmatch($pattern, $command)) {
            return $index;
        }
    }

    return -1;
}

/**
 * @return FakeSSHConnection
 */
function stubStaticDeploymentSsh(string $domain, string $deploymentId): FakeSSHConnection
{
    $fake = stubDeploymentSsh($domain, $deploymentId);
    $success = static fn (): SSHResult => sshSuccess();

    $fake->addSequence('test -f *', sshFailure());
    $fake->addSequence('sudo nginx -t', $success());
    $fake->addSequence('sudo systemctl reload nginx', $success());

    return $fake;
}

function stubDeploymentSsh(string $domain, string $deploymentId): FakeSSHConnection
{
    $fake = (new FakeSSHConnection())->connect();
    $releasePath = '/var/www/'.$domain.'/releases/'.$deploymentId;
    $success = static fn (): SSHResult => sshSuccess();

    $responses = [
        'echo "_ok_"' => sshSuccess('_ok_'),
        'df -h / | awk *' => sshSuccess('10G'),
        'mkdir -p *' => [$success(), $success()],
        'git clone *' => $success(),
        'git -C * rev-parse HEAD' => sshSuccess('deadbeef'),
        'git -C * log -1 *' => sshSuccess('Deploy commit'),
        '*composer install*' => $success(),
        'test -f *' => array_fill(0, 10, sshFailure()),
        'chmod 640 *' => $success(),
        'chown deploy:www-data *' => $success(),
        'ln -sfn *' => [$success(), $success(), $success()],
        'rm -rf *' => [$success(), $success(), $success()],
        '*php artisan config:cache*' => $success(),
        '*php artisan route:cache*' => $success(),
        '*php artisan view:cache*' => $success(),
        'readlink -f *' => [sshSuccess($releasePath), sshSuccess($releasePath)],
        '*systemctl reload php*' => $success(),
        'ls -1dt *' => sshSuccess($releasePath),
    ];

    foreach ($responses as $pattern => $result) {
        if (is_array($result)) {
            $fake->addSequence($pattern, ...$result);

            continue;
        }

        $fake->addSequence($pattern, $result);
    }

    return $fake;
}
