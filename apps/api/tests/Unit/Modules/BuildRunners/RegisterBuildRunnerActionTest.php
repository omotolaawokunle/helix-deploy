<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\BuildRunners\Actions\RegisterBuildRunnerAction;
use App\Modules\BuildRunners\DTOs\RegisterBuildRunnerDTO;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Jobs\VerifyBuildRunnerConnectionJob;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;

it('registers a build runner with generated key and connecting status', function (): void {
    Queue::fake();

    [$organization, $owner] = createBuildRunnerActionFixture();
    $action = app(RegisterBuildRunnerAction::class);

    $result = $action->execute($organization, $owner, new RegisterBuildRunnerDTO(
        name: 'Main Runner',
        ipAddress: '192.168.10.5',
        sshPort: 22,
        sshUser: 'deploy',
        authMethod: 'generate',
        privateKey: null,
        maxConcurrentBuilds: 2,
        cpuCores: 4,
        ramGb: 8,
        supportedRuntimes: ['php', 'nodejs'],
        projectId: null,
    ));

    $runner = $result['runner'];

    expect($result['publicKey'])->toBeString()->not->toBe('');
    expect($runner->status)->toBe(BuildRunnerStatus::CONNECTING);
    expect($runner->credential_id)->not->toBeNull();
    expect(Credential::query()->whereKey($runner->credential_id)->exists())->toBeTrue();
    expect(AuditLog::query()->where('operation', 'build_runner.registered')->exists())->toBeTrue();

    Queue::assertPushed(VerifyBuildRunnerConnectionJob::class);
});

it('registers a build runner with imported key and connecting status', function (): void {
    Queue::fake();

    [$organization, $owner] = createBuildRunnerActionFixture();
    $action = app(RegisterBuildRunnerAction::class);

    $result = $action->execute($organization, $owner, new RegisterBuildRunnerDTO(
        name: 'Imported Runner',
        ipAddress: '10.10.10.10',
        sshPort: 2222,
        sshUser: 'root',
        authMethod: 'import',
        privateKey: "-----BEGIN OPENSSH PRIVATE KEY-----\nabc\n-----END OPENSSH PRIVATE KEY-----",
        maxConcurrentBuilds: 1,
        cpuCores: null,
        ramGb: null,
        supportedRuntimes: ['php'],
        projectId: null,
    ));

    $runner = $result['runner'];

    expect($result['publicKey'])->toBeNull();
    expect($runner->status)->toBe(BuildRunnerStatus::CONNECTING);
    expect($runner->credential_id)->not->toBeNull();

    Queue::assertPushed(VerifyBuildRunnerConnectionJob::class);
});

/**
 * @return array{Organization, User}
 */
function createBuildRunnerActionFixture(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $organization = Organization::query()->create([
        'name' => 'Build Runners Org',
        'slug' => 'build-runners-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner->forceFill(['current_organization_id' => (string) $organization->getKey()])->save();

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    return [$organization, $owner];
}
