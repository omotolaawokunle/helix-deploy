<?php

declare(strict_types=1);

use App\Modules\BuildRunners\Enums\ArtifactStorageType;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Models\BuildArtifact;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Enums\Runtime;
use App\Models\User;
use App\Packages\Artifacts\ArtifactManager;
use App\Packages\Artifacts\Exceptions\ArtifactCorruptedException;
use App\Packages\Artifacts\Transfers\ScpArtifactTransfer;
use App\Packages\Artifacts\Transfers\S3ArtifactTransfer;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Str;

it('creates an artifact tarball and persists checksum metadata', function (): void {
    [$organization, $server, $site, $deployment] = executionFixture();
    $runner = createArtifactTestRunner($organization, User::query()->findOrFail($deployment->triggered_by));
    $ssh = (new FakeSSHConnection())->connect();

    $buildPath = '/builds/'.$deployment->getKey().'/';
    $artifactPath = '/tmp/'.$deployment->getKey().'.tar.gz';
    $checksum = str_repeat('a', 64);

    queueSshResponses($ssh, [
        'tar -czf*' => sshSuccess(),
        'sha256sum*' => sshSuccess($checksum.'  '.$artifactPath),
        'stat -c*' => sshSuccess('15000000'),
    ]);

    $artifact = (new ArtifactManager())->create(
        ssh: $ssh,
        buildPath: $buildPath,
        artifactPath: $artifactPath,
        runner: $runner,
        deployment: $deployment,
    );

    $ssh->assertCommandExecuted('tar -czf*');
    $ssh->assertCommandExecuted('sha256sum*');
    $ssh->assertCommandExecuted('stat -c*');

    expect($artifact)->toBeInstanceOf(BuildArtifact::class)
        ->and($artifact->storage_type)->toBe(ArtifactStorageType::LOCAL)
        ->and($artifact->storage_path)->toBe($artifactPath)
        ->and($artifact->checksum)->toBe($checksum)
        ->and($artifact->size_bytes)->toBe(15_000_000)
        ->and($artifact->runtime)->toBe(Runtime::PHP->value)
        ->and($artifact->runner_id)->toBe((string) $runner->getKey());
});

it('verifies artifact checksums and throws when they mismatch', function (): void {
    $ssh = (new FakeSSHConnection())->connect();
    $path = '/tmp/example.tar.gz';
    $expected = str_repeat('b', 64);

    queueSshResponses($ssh, [
        'sha256sum*' => sshSuccess($expected.'  '.$path),
    ]);

    (new ArtifactManager())->verify($expected, $ssh, $path);

    $mismatchSsh = (new FakeSSHConnection())->connect();
    queueSshResponses($mismatchSsh, [
        'sha256sum*' => sshSuccess(str_repeat('c', 64).'  '.$path),
    ]);

    expect(fn () => (new ArtifactManager())->verify($expected, $mismatchSsh, $path))
        ->toThrow(ArtifactCorruptedException::class);
});

it('transfers artifacts over scp and verifies checksum on the target server', function (): void {
    [, $server, , $deployment] = executionFixture();
    $runnerSsh = (new FakeSSHConnection())->connect();
    $targetSsh = (new FakeSSHConnection())->connect();

    $runnerPath = '/tmp/'.$deployment->getKey().'.tar.gz';
    $targetPath = '/tmp/'.$deployment->getKey().'.tar.gz';
    $checksum = str_repeat('d', 64);

    queueSshResponses($runnerSsh, [
        'test -f*' => sshSuccess(),
        'scp -o StrictHostKeyChecking=no*' => sshSuccess(),
    ]);
    queueSshResponses($targetSsh, [
        'sha256sum*' => sshSuccess($checksum.'  '.$targetPath),
    ]);

    (new ScpArtifactTransfer($server))->transfer(
        runnerSsh: $runnerSsh,
        targetSsh: $targetSsh,
        runnerPath: $runnerPath,
        targetPath: $targetPath,
        expectedChecksum: $checksum,
    );

    $runnerSsh->assertCommandExecuted('test -f*');
    $runnerSsh->assertCommandExecuted('scp -o StrictHostKeyChecking=no -P 22 *'.$server->ip_address.':'.$targetPath);
    $targetSsh->assertCommandExecuted('sha256sum*');
});

it('throws when scp transfer checksum verification fails on the target server', function (): void {
    [, $server, , $deployment] = executionFixture();
    $runnerSsh = (new FakeSSHConnection())->connect();
    $targetSsh = (new FakeSSHConnection())->connect();

    $runnerPath = '/tmp/'.$deployment->getKey().'.tar.gz';
    $targetPath = '/tmp/'.$deployment->getKey().'.tar.gz';
    $expectedChecksum = str_repeat('e', 64);

    queueSshResponses($runnerSsh, [
        'test -f*' => sshSuccess(),
        'scp -o StrictHostKeyChecking=no*' => sshSuccess(),
    ]);
    queueSshResponses($targetSsh, [
        'sha256sum*' => sshSuccess(str_repeat('f', 64).'  '.$targetPath),
    ]);

    expect(fn () => (new ScpArtifactTransfer($server))->transfer(
        runnerSsh: $runnerSsh,
        targetSsh: $targetSsh,
        runnerPath: $runnerPath,
        targetPath: $targetPath,
        expectedChecksum: $expectedChecksum,
    ))->toThrow(ArtifactCorruptedException::class);
});

it('rejects s3 artifact transfer in v1', function (): void {
    expect(fn () => (new S3ArtifactTransfer())->transfer(
        runnerSsh: (new FakeSSHConnection())->connect(),
        targetSsh: (new FakeSSHConnection())->connect(),
        runnerPath: '/tmp/a.tar.gz',
        targetPath: '/tmp/a.tar.gz',
        expectedChecksum: str_repeat('0', 64),
    ))->toThrow(RuntimeException::class, 'S3 artifact transfer is not implemented in v1. Use SCP.');
});

function createArtifactTestRunner(Organization $organization, User $owner): BuildRunner
{
    return BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'artifact-build-'.Str::random(4),
        'ip_address' => '10.0.0.60',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => 1,
        'supported_runtimes' => ['php'],
        'created_by' => (string) $owner->getKey(),
    ]);
}
