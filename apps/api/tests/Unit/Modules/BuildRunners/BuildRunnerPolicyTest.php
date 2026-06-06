<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Policies\BuildRunnerPolicy;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Redis;

beforeEach(function (): void {
    useInMemoryRunnerSlotStore();
});

it('allows owners to delete runners without active slots', function (): void {
    [$organization, $owner, $runner] = createBuildRunnerPolicyFixture();

    $policy = new BuildRunnerPolicy();

    expect($policy->delete($owner, $runner))->toBeTrue();
});

it('blocks delete when active build slots exist', function (): void {
    [$organization, $owner, $runner] = createBuildRunnerPolicyFixture(maxConcurrentBuilds: 1);

    app(RunnerSlotManager::class)->acquire($runner, 'active-build');

    $policy = new BuildRunnerPolicy();

    expect($policy->delete($owner, $runner))->toBeFalse();
});

it('allows admins to manage but not delete runners', function (): void {
    [$organization, $owner, $runner] = createBuildRunnerPolicyFixture();

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($admin->getKey(), ['role' => TeamRole::ADMIN->value]);

    $policy = new BuildRunnerPolicy();

    expect($policy->update($admin, $runner))->toBeTrue()
        ->and($policy->delete($admin, $runner))->toBeFalse();
});

/**
 * @return array{Organization, User, BuildRunner}
 */
function createBuildRunnerPolicyFixture(int $maxConcurrentBuilds = 2): array
{
    $organization = Organization::query()->create([
        'name' => 'Build Runner Policy Org',
        'slug' => 'build-runner-policy-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $runner = BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Policy Runner',
        'ip_address' => '10.0.0.55',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => $maxConcurrentBuilds,
        'supported_runtimes' => ['php'],
        'created_by' => (string) $owner->getKey(),
    ]);

    return [$organization, $owner, $runner];
}
