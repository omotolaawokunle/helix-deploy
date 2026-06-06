<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Events\BuildRunnerOffline;
use App\Modules\BuildRunners\Events\BuildRunnerOnline;
use App\Modules\BuildRunners\Jobs\PingBuildRunnersJob;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\BuildRunnerSSHManager;
use App\Packages\SSH\FakeSSHConnection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

it('marks build runner offline after three consecutive ping failures', function (): void {
    Event::fake([BuildRunnerOffline::class, BuildRunnerOnline::class]);

    $runner = createPingTestRunner(BuildRunnerStatus::ONLINE);
    $job = new PingBuildRunnersJob();

    $manager = \Mockery::mock(BuildRunnerSSHManager::class);
    $manager->shouldReceive('connect')->times(3)->andThrow(new RuntimeException('ping failed'));

    Redis::shouldReceive('incr')->times(3)->with("runner_ping_failures:{$runner->id}")->andReturn(1, 2, 3);
    Redis::shouldReceive('del')->never();

    $vault = app(\App\Modules\Credentials\CredentialVault::class);

    $job->handle($manager, $vault);
    $job->handle($manager, $vault);
    $job->handle($manager, $vault);

    $runner->refresh();

    expect($runner->status)->toBe(BuildRunnerStatus::OFFLINE);
    Event::assertDispatched(BuildRunnerOffline::class);
});

it('marks offline build runner online after a successful ping', function (): void {
    Event::fake([BuildRunnerOffline::class, BuildRunnerOnline::class]);

    $runner = createPingTestRunner(BuildRunnerStatus::OFFLINE);
    $job = new PingBuildRunnersJob();

    $fake = new FakeSSHConnection();
    $fake->addSequence('echo "_ping_"*', sshSuccess('_ping_'));

    $manager = \Mockery::mock(BuildRunnerSSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);

    Redis::shouldReceive('del')->once()->with("runner_ping_failures:{$runner->id}");
    Redis::shouldReceive('incr')->never();

    $vault = app(\App\Modules\Credentials\CredentialVault::class);

    $job->handle($manager, $vault);

    $runner->refresh();

    expect($runner->status)->toBe(BuildRunnerStatus::ONLINE);
    Event::assertDispatched(BuildRunnerOnline::class);
});

it('skips ping when runner has an active building deployment', function (): void {
    [, , , $deployment] = executionFixture();

    $runner = createPingTestRunner(BuildRunnerStatus::ONLINE);
    $deployment->forceFill([
        'status' => \App\Modules\Deployments\Enums\DeploymentStatus::BUILDING,
        'build_runner_id' => (string) $runner->getKey(),
        'started_at' => now(),
    ])->save();

    $manager = \Mockery::mock(BuildRunnerSSHManager::class);
    $manager->shouldReceive('connect')->never();

    Redis::shouldReceive('incr')->never();
    Redis::shouldReceive('del')->never();

    (new PingBuildRunnersJob())->handle($manager, app(\App\Modules\Credentials\CredentialVault::class));
});

function createPingTestRunner(BuildRunnerStatus $status): BuildRunner
{
    $organization = Organization::query()->create([
        'name' => 'Ping Runner Org',
        'slug' => 'ping-runner-org-'.Str::random(4),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $credential = Credential::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'ssh_private_key',
        'name' => 'Runner Key',
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => 'fp',
        'created_by' => (string) $owner->getKey(),
        'last_used_at' => null,
    ]);

    return BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Ping Runner',
        'ip_address' => '10.0.0.55',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => $status->value,
        'max_concurrent_builds' => 2,
        'supported_runtimes' => ['php'],
        'credential_id' => (string) $credential->getKey(),
        'created_by' => (string) $owner->getKey(),
    ]);
}
