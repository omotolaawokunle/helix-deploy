<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\BuildRunners\Enums\ArtifactStorageType;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Enums\BuildStrategy;
use App\Modules\BuildRunners\Models\BuildArtifact;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Enums\Runtime;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('reports whether a build runner supports a runtime', function (): void {
    $runner = new BuildRunner([
        'supported_runtimes' => ['php', 'nodejs'],
        'status' => BuildRunnerStatus::ONLINE->value,
    ]);

    expect($runner->supportsRuntime(Runtime::PHP))->toBeTrue()
        ->and($runner->supportsRuntime(Runtime::NODEJS))->toBeTrue()
        ->and($runner->supportsRuntime(Runtime::PYTHON))->toBeFalse()
        ->and($runner->isAvailable())->toBeTrue();
});

it('marks offline runners as unavailable', function (): void {
    $runner = new BuildRunner([
        'supported_runtimes' => ['go'],
        'status' => BuildRunnerStatus::OFFLINE->value,
    ]);

    expect($runner->isAvailable())->toBeFalse();
});

it('formats artifact sizes and detects expiration', function (): void {
    $artifact = new BuildArtifact([
        'size_bytes' => 15_000_000,
        'expires_at' => now()->subMinute(),
    ]);

    expect($artifact->formattedSize())->toBe('14.3 MB')
        ->and($artifact->isExpired())->toBeTrue();

    $freshArtifact = new BuildArtifact([
        'size_bytes' => 512,
        'expires_at' => null,
    ]);

    expect($freshArtifact->formattedSize())->toBe('512 B')
        ->and($freshArtifact->isExpired())->toBeFalse();
});

it('casts deployment build strategy values', function (): void {
    [$organization, $owner, $deployment] = buildRunnerFixture();

    $deployment->forceFill(['build_strategy' => BuildStrategy::ON_SERVER->value])->save();
    $deployment->refresh();
    expect($deployment->build_strategy)->toBe(BuildStrategy::ON_SERVER)
        ->and($deployment->isOnServerBuild())->toBeTrue()
        ->and($deployment->isRunnerBuild())->toBeFalse();

    $deployment->forceFill(['build_strategy' => BuildStrategy::RUNNER->value])->save();
    $deployment->refresh();
    expect($deployment->build_strategy)->toBe(BuildStrategy::RUNNER)
        ->and($deployment->isRunnerBuild())->toBeTrue();

    $deployment->forceFill(['build_strategy' => BuildStrategy::EXTERNAL->value])->save();
    $deployment->refresh();
    expect($deployment->build_strategy)->toBe(BuildStrategy::EXTERNAL);
});

it('defaults existing deployments to on_server after migration', function (): void {
    [$organization, $owner, $deployment] = buildRunnerFixture();

    expect(Schema::hasColumn('deployments', 'build_strategy'))->toBeTrue()
        ->and(Schema::hasColumn('deployments', 'build_runner_id'))->toBeTrue()
        ->and(Schema::hasColumn('deployments', 'build_artifact_id'))->toBeTrue()
        ->and($deployment->build_strategy)->toBe(BuildStrategy::ON_SERVER)
        ->and($deployment->build_runner_id)->toBeNull()
        ->and($deployment->build_artifact_id)->toBeNull();
});

it('scopes build artifacts to the authenticated organization', function (): void {
    [$firstOrg, $firstOwner, $firstDeployment] = buildRunnerFixture();
    [$secondOrg, $secondOwner, $secondDeployment] = buildRunnerFixture();

    $firstRunner = createBuildRunnerRecord($firstOrg, $firstOwner);
    $secondRunner = createBuildRunnerRecord($secondOrg, $secondOwner);

    BuildArtifact::query()->withoutGlobalScope('owned_by_organization')->create([
        'id' => (string) Str::uuid(),
        'organization_id' => (string) $firstOrg->getKey(),
        'deployment_id' => (string) $firstDeployment->getKey(),
        'runner_id' => (string) $firstRunner->getKey(),
        'storage_type' => ArtifactStorageType::LOCAL->value,
        'storage_path' => '/tmp/first.tar.gz',
        'checksum' => str_repeat('a', 64),
        'size_bytes' => 1024,
        'runtime' => Runtime::PHP->value,
        'created_at' => now(),
    ]);

    BuildArtifact::query()->withoutGlobalScope('owned_by_organization')->create([
        'id' => (string) Str::uuid(),
        'organization_id' => (string) $secondOrg->getKey(),
        'deployment_id' => (string) $secondDeployment->getKey(),
        'runner_id' => (string) $secondRunner->getKey(),
        'storage_type' => ArtifactStorageType::LOCAL->value,
        'storage_path' => '/tmp/second.tar.gz',
        'checksum' => str_repeat('b', 64),
        'size_bytes' => 2048,
        'runtime' => Runtime::NODEJS->value,
        'created_at' => now(),
    ]);

    $this->actingAs($firstOwner);

    $visible = BuildArtifact::query()->get();

    expect($visible)->toHaveCount(1)
        ->and((string) $visible->first()?->organization_id)->toBe((string) $firstOrg->getKey());
});

/**
 * @return array{Organization, User, Deployment}
 */
function buildRunnerFixture(): array
{
    [$organization, $server, $site, $deployment] = executionFixture();

    return [$organization, User::query()->findOrFail($deployment->triggered_by), $deployment];
}

function createBuildRunnerRecord(Organization $organization, User $owner): BuildRunner
{
    return BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'build-'.Str::random(4),
        'ip_address' => '10.0.0.50',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => 2,
        'supported_runtimes' => ['php', 'nodejs'],
        'created_by' => (string) $owner->getKey(),
    ]);
}
