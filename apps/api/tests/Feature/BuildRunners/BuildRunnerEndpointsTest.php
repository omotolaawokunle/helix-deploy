<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Jobs\VerifyBuildRunnerConnectionJob;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    useInMemoryRunnerSlotStore();
});

/**
 * @return array{Organization, User}
 */
function createBuildRunnerEndpointFixture(TeamRole $role = TeamRole::OWNER): array
{
    $organization = Organization::query()->create([
        'name' => 'Build Runner Endpoint Org',
        'slug' => 'build-runner-endpoints-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($user->getKey(), ['role' => $role->value]);

    return [$organization, $user];
}

it('registers a build runner and never exposes credential or fingerprint fields', function (): void {
    Queue::fake();

    [$organization, $owner] = createBuildRunnerEndpointFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/build-runners", [
            'name' => 'CI Runner',
            'ipAddress' => '203.0.113.50',
            'sshPort' => 22,
            'sshUser' => 'deploy',
            'authMethod' => 'generate',
            'maxConcurrentBuilds' => 2,
            'cpuCores' => 4,
            'ramGb' => 8,
            'supportedRuntimes' => ['php', 'nodejs'],
        ])
        ->assertOk()
        ->assertJsonPath('data.runner.name', 'CI Runner')
        ->assertJsonPath('data.runner.status', BuildRunnerStatus::CONNECTING->value)
        ->assertJsonPath('data.runner.availableSlots', 2)
        ->assertJsonMissing(['credentialId', 'credential_id', 'fingerprint'])
        ->assertJsonStructure(['data' => ['publicKey', 'runner']]);

    expect(AuditLog::query()->where('operation', 'build_runner.registered')->exists())->toBeTrue();

    Queue::assertPushed(VerifyBuildRunnerConnectionJob::class);
});

it('forbids cross organization build runner access', function (): void {
    [$firstOrg, $firstOwner] = createBuildRunnerEndpointFixture();

    $secondOrg = Organization::query()->create([
        'name' => 'Second Build Runner Org',
        'slug' => 'second-build-runner-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $secondOrg->generateAndStoreMasterKey();

    $secondOwner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $secondOrg->getKey(),
    ]);
    $secondOrg->users()->attach($secondOwner->getKey(), ['role' => TeamRole::OWNER->value]);

    $runner = BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $secondOrg->getKey(),
        'name' => 'Private Runner',
        'ip_address' => '10.12.0.44',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => 1,
        'supported_runtimes' => ['php'],
        'created_by' => (string) $secondOwner->getKey(),
    ]);

    $this->actingAs($firstOwner)
        ->getJson("/api/v1/build-runners/{$runner->id}")
        ->assertForbidden();
});

it('blocks deleting a runner while active slots are in use', function (): void {
    [$organization, $owner] = createBuildRunnerEndpointFixture();

    $runner = BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Busy Runner',
        'ip_address' => '10.12.0.45',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => 1,
        'supported_runtimes' => ['php'],
        'created_by' => (string) $owner->getKey(),
    ]);

    app(RunnerSlotManager::class)->acquire($runner, 'active-build');

    $this->actingAs($owner)
        ->deleteJson("/api/v1/build-runners/{$runner->id}")
        ->assertForbidden();
});

it('organization build runners index supports pagination and filters', function (): void {
    [$organization, $owner] = createBuildRunnerEndpointFixture();

    foreach (range(1, 16) as $index) {
        BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
            'organization_id' => (string) $organization->getKey(),
            'name' => "runner-{$index}",
            'ip_address' => "10.20.0.{$index}",
            'ssh_port' => 22,
            'ssh_user' => 'deploy',
            'status' => $index % 2 === 0 ? BuildRunnerStatus::ONLINE->value : BuildRunnerStatus::OFFLINE->value,
            'max_concurrent_builds' => 1,
            'supported_runtimes' => ['php'],
            'created_by' => (string) $owner->getKey(),
        ]);
    }

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/build-runners")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonCount(15, 'data');

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/build-runners?filter[status]=online&search=runner-16")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'runner-16');
});

it('dispatches a connection test for an existing runner', function (): void {
    Queue::fake();

    [$organization, $owner] = createBuildRunnerEndpointFixture();

    $runner = BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Test Runner',
        'ip_address' => '10.12.0.46',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => 1,
        'supported_runtimes' => ['php'],
        'created_by' => (string) $owner->getKey(),
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/build-runners/{$runner->id}/test-connection")
        ->assertAccepted();

    Queue::assertPushed(VerifyBuildRunnerConnectionJob::class);
});
