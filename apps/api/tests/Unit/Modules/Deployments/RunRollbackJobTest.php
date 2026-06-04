<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Deployments\Events\DeploymentCompleted;
use App\Modules\Deployments\Jobs\RunRollbackJob;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\DeploymentStep;
use App\Modules\Deployments\Models\Release;
use App\Packages\Execution\DeploymentRunner;
use App\Packages\Execution\PipelineBuilder;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\Event;

it('executes symlink rollback with original release path', function (): void {
    Event::fake([DeploymentCompleted::class]);

    [$rollback, $original, $releasePath, $site] = rollbackJobFixture();

    $fake = stubRollbackSsh($site->domain, $releasePath);

    $this->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    $job = new RunRollbackJob((string) $rollback->getKey(), (string) $rollback->triggered_by);
    $job->handle(
        new PipelineBuilder(),
        new DeploymentRunner(),
        app(SSHManager::class),
        app(\App\Modules\Credentials\CredentialVault::class),
    );

    $rollback->refresh();
    expect($rollback->status)->toBe(DeploymentStatus::SUCCESS);

    $commands = $fake->getExecutedCommands();
    $symlinkCommand = collect($commands)->first(fn (string $cmd): bool => str_contains($cmd, 'ln -sfn'));
    expect($symlinkCommand)->toContain($releasePath);

    $targetRelease = Release::query()
        ->where('deployment_id', (string) $original->getKey())
        ->where('path', $releasePath)
        ->first();

    expect($targetRelease?->is_active)->toBeTrue();

    expect(AuditLog::query()->where('operation', 'deployment.rollback_completed')->exists())->toBeTrue();

    Event::assertDispatched(DeploymentCompleted::class);
});

it('creates ordered rollback pipeline steps', function (): void {
    Event::fake([DeploymentCompleted::class]);

    [$rollback, , $releasePath, $site] = rollbackJobFixture();

    $fake = stubRollbackSsh($site->domain, $releasePath);
    $this->mock(SSHManager::class, fn ($mock) => $mock->shouldReceive('connect')->once()->andReturn($fake));

    $job = new RunRollbackJob((string) $rollback->getKey(), (string) $rollback->triggered_by);
    $job->handle(
        new PipelineBuilder(),
        new DeploymentRunner(),
        app(SSHManager::class),
        app(\App\Modules\Credentials\CredentialVault::class),
    );

    $steps = DeploymentStep::query()
        ->where('deployment_id', $rollback->getKey())
        ->orderBy('order')
        ->pluck('name')
        ->all();

    expect($steps)->toBe([
        'verify-connection',
        'verify-release-exists',
        'activate-release',
        'reload-services',
    ]);
});

/**
 * @return array{0: Deployment, 1: Deployment, 2: string, 3: \App\Modules\Sites\Models\Site}
 */
function rollbackJobFixture(): array
{
    [, $server, $site, $userDeployment] = executionFixture();
    $userDeployment->forceFill(['status' => DeploymentStatus::SUCCESS, 'finished_at' => now()])->save();
    $site->forceFill(['run_migrations' => false])->save();

    $credential = \App\Modules\Credentials\Models\Credential::query()->create([
        'organization_id' => (string) $site->organization_id,
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'ssh_private_key',
        'name' => 'Rollback Job Key',
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => 'fp',
        'created_by' => $userDeployment->triggered_by,
        'last_used_at' => null,
    ]);
    $server->forceFill(['credential_id' => (string) $credential->getKey()])->save();

    $releasePath = '/var/www/'.$site->domain.'/releases/target-release';

    $original = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $site->organization_id,
        'type' => DeploymentType::DEPLOY,
        'status' => DeploymentStatus::SUCCESS,
        'triggered_by' => $userDeployment->triggered_by,
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

    $brokenDeploy = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $site->organization_id,
        'type' => DeploymentType::DEPLOY,
        'status' => DeploymentStatus::SUCCESS,
        'triggered_by' => $userDeployment->triggered_by,
        'trigger_type' => TriggerType::MANUAL,
        'branch' => 'main',
        'finished_at' => now(),
    ]);

    Release::query()->create([
        'site_id' => (string) $site->getKey(),
        'deployment_id' => (string) $brokenDeploy->getKey(),
        'organization_id' => (string) $site->organization_id,
        'path' => '/var/www/'.$site->domain.'/releases/broken',
        'commit_hash' => 'badcafe',
        'is_active' => true,
        'created_at' => now(),
    ]);

    $rollback = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $site->organization_id,
        'type' => DeploymentType::ROLLBACK,
        'status' => DeploymentStatus::PENDING,
        'triggered_by' => $userDeployment->triggered_by,
        'trigger_type' => TriggerType::MANUAL,
        'release_path' => $releasePath,
        'rollback_target_id' => (string) $original->getKey(),
        'rollback_reason' => null,
    ]);

    return [$rollback, $original, $releasePath, $site];
}

function stubRollbackSsh(string $domain, string $releasePath): FakeSSHConnection
{
    $fake = (new FakeSSHConnection())->connect();
    $success = static fn (): SSHResult => sshSuccess();

    $responses = [
        'echo "_ok_"' => sshSuccess('_ok_'),
        'df -h / | awk *' => sshSuccess('10G'),
        'test -d *' => sshSuccess('exists'),
        'test -f *' => sshFailure(),
        'ln -sfn *' => [$success(), $success()],
        'readlink -f *' => [sshSuccess($releasePath), sshSuccess($releasePath)],
        '*systemctl reload php*' => $success(),
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
