<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\Actions\RollbackDeploymentAction;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Deployments\Exceptions\ObserveModeServerException;
use App\Modules\Deployments\Exceptions\ProductionRollbackReasonRequiredException;
use App\Modules\Deployments\Exceptions\ReleaseNotFoundException;
use App\Modules\Deployments\Jobs\RunRollbackJob;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\Release;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Enums\ManagementMode;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('creates rollback deployment linked to original and dispatches job', function (): void {
    Queue::fake();

    [$original, $actor, $releasePath] = rollbackTargetFixture();

    $fake = (new FakeSSHConnection())->connect();
    $fake->addSequence('test -d *', sshSuccess('exists'));

    $this->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    $rollback = app(RollbackDeploymentAction::class)->execute($original, $actor);

    expect($rollback->type)->toBe(DeploymentType::ROLLBACK)
        ->and($rollback->status)->toBe(DeploymentStatus::PENDING)
        ->and($rollback->rollback_target_id)->toBe((string) $original->getKey())
        ->and($rollback->release_path)->toBe($releasePath);

    Queue::assertPushed(RunRollbackJob::class, function (RunRollbackJob $job) use ($rollback): bool {
        return $job->deploymentId === (string) $rollback->getKey();
    });

    $audit = AuditLog::query()->where('operation', 'deployment.rollback_triggered')->first();
    expect($audit)->not->toBeNull()
        ->and($audit?->after_state['originalDeploymentId'])->toBe((string) $original->getKey())
        ->and($audit?->after_state['releasePath'])->toBe($releasePath);
});

it('returns 404 when release directory is missing on server', function (): void {
    [$original, $actor] = rollbackTargetFixture();

    $fake = (new FakeSSHConnection())->connect();
    $fake->addSequence('test -d *', sshSuccess('missing'));

    $this->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    expect(fn () => app(RollbackDeploymentAction::class)->execute($original, $actor))
        ->toThrow(ReleaseNotFoundException::class);
});

it('requires production rollback reason with at least 10 characters', function (): void {
    [$original, $actor] = rollbackTargetFixture(isProduction: true);

    $fake = (new FakeSSHConnection())->connect();
    $fake->addSequence('test -d *', sshSuccess('exists'));

    $this->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->never();
    });

    expect(fn () => app(RollbackDeploymentAction::class)->execute($original, $actor))
        ->toThrow(ProductionRollbackReasonRequiredException::class);
});

it('accepts production rollback when reason is provided', function (): void {
    Queue::fake();

    [$original, $actor, $releasePath] = rollbackTargetFixture(isProduction: true);

    $fake = (new FakeSSHConnection())->connect();
    $fake->addSequence('test -d *', sshSuccess('exists'));

    $this->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    $reason = 'Reverting bad deploy from monitoring alert';
    $rollback = app(RollbackDeploymentAction::class)->execute($original, $actor, $reason);

    expect($rollback->rollback_reason)->toBe($reason);

    $audit = AuditLog::query()->where('operation', 'deployment.rollback_triggered')->first();
    expect($audit?->after_state['reason'])->toBe($reason)
        ->and($audit?->after_state['isProduction'])->toBeTrue()
        ->and($audit?->after_state['releasePath'])->toBe($releasePath);
});

it('rejects rollback on observe mode server', function (): void {
    [$original, $actor, , $server] = rollbackTargetFixture();
    $server->forceFill(['management_mode' => ManagementMode::OBSERVE])->save();

    expect(fn () => app(RollbackDeploymentAction::class)->execute($original, $actor))
        ->toThrow(ObserveModeServerException::class);
});

it('records before and after release state in audit log', function (): void {
    Queue::fake();

    [$original, $actor, $releasePath, , $site] = rollbackTargetFixture();

    $badDeploy = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $site->organization_id,
        'type' => DeploymentType::DEPLOY,
        'status' => DeploymentStatus::SUCCESS,
        'triggered_by' => $original->triggered_by,
        'trigger_type' => TriggerType::MANUAL,
        'branch' => 'main',
        'finished_at' => now(),
    ]);

    $currentRelease = Release::query()->create([
        'site_id' => (string) $site->getKey(),
        'deployment_id' => (string) $badDeploy->getKey(),
        'organization_id' => (string) $site->organization_id,
        'path' => '/var/www/'.$site->domain.'/releases/current-bad',
        'commit_hash' => 'badcafe',
        'is_active' => true,
        'created_at' => now(),
    ]);

    $fake = (new FakeSSHConnection())->connect();
    $fake->addSequence('test -d *', sshSuccess('exists'));

    $this->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    app(RollbackDeploymentAction::class)->execute($original, $actor);

    $audit = AuditLog::query()->where('operation', 'deployment.rollback_triggered')->first();

    expect($audit?->before_state['activeReleasePath'])->toBe($currentRelease->path)
        ->and($audit?->after_state['releasePath'])->toBe($releasePath);
});

/**
 * @return array{0: Deployment, 1: \App\Models\User, 2: string, 3: \App\Modules\Servers\Models\Server, 4: \App\Modules\Sites\Models\Site, 5: Deployment}
 */
function rollbackTargetFixture(bool $isProduction = false): array
{
    [, $server, $site, $deployment] = executionFixture();
    $deployment->forceFill(['status' => DeploymentStatus::SUCCESS, 'finished_at' => now()])->save();

    $credential = \App\Modules\Credentials\Models\Credential::query()->create([
        'organization_id' => (string) $site->organization_id,
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'ssh_private_key',
        'name' => 'Rollback Key',
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => 'fp',
        'created_by' => $deployment->triggered_by,
        'last_used_at' => null,
    ]);
    $server->forceFill(['credential_id' => (string) $credential->getKey()])->save();

    if ($isProduction) {
        $environment = Environment::query()->findOrFail($site->environment_id);
        $environment->forceFill(['is_production' => true])->save();
    } else {
        $project = Project::query()->create([
            'organization_id' => (string) $site->organization_id,
            'name' => 'Staging Project',
        ]);
        $staging = Environment::query()->create([
            'project_id' => (string) $project->getKey(),
            'organization_id' => (string) $site->organization_id,
            'name' => 'staging',
            'label' => 'Staging',
            'is_production' => false,
        ]);
        $site->forceFill(['environment_id' => (string) $staging->getKey()])->save();
    }

    $releasePath = '/var/www/'.$site->domain.'/releases/'.Str::uuid();

    $original = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $site->organization_id,
        'type' => DeploymentType::DEPLOY,
        'status' => DeploymentStatus::SUCCESS,
        'triggered_by' => $deployment->triggered_by,
        'trigger_type' => TriggerType::MANUAL,
        'branch' => 'main',
        'release_path' => $releasePath,
        'finished_at' => now(),
    ]);

    Release::query()->create([
        'site_id' => (string) $site->getKey(),
        'deployment_id' => (string) $original->getKey(),
        'organization_id' => (string) $site->organization_id,
        'path' => $releasePath,
        'commit_hash' => 'deadbeef',
        'is_active' => false,
        'created_at' => now()->subHour(),
    ]);

    $actor = \App\Models\User::query()->findOrFail($deployment->triggered_by);

    return [$original, $actor, $releasePath, $server, $site, $deployment];
}
