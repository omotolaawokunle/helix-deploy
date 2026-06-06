<?php

declare(strict_types=1);

namespace App\Packages\Artifacts;

use App\Modules\BuildRunners\Enums\ArtifactStorageType;
use App\Modules\BuildRunners\Models\BuildArtifact;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Deployments\Models\Deployment;
use App\Packages\Artifacts\Exceptions\ArtifactCorruptedException;
use App\Packages\Artifacts\Support\Sha256OutputParser;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\Exceptions\SSHCommandFailedException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ArtifactManager
{
    public function create(
        SSHConnectionInterface $ssh,
        string $buildPath,
        string $artifactPath,
        BuildRunner $runner,
        Deployment $deployment,
    ): BuildArtifact {
        $deployment->loadMissing('site');
        $site = $deployment->site;

        if ($site === null) {
            throw new \InvalidArgumentException('Deployment site is required to create a build artifact.');
        }

        $quotedBuildPath = escapeshellarg($buildPath);
        $quotedArtifactPath = escapeshellarg($artifactPath);

        $ssh->run(sprintf(
            'tar -czf %s -C %s . --exclude=%s',
            $quotedArtifactPath,
            $quotedBuildPath,
            escapeshellarg('.git'),
        ))->throw();

        $checksumResult = $ssh->run(sprintf('sha256sum %s', $quotedArtifactPath))->throw();
        $checksum = Sha256OutputParser::parse($checksumResult->stdout);

        $sizeResult = $ssh->run(sprintf('stat -c%%s %s', $quotedArtifactPath))->throw();
        $sizeBytes = (int) trim($sizeResult->stdout);

        return BuildArtifact::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => (string) $deployment->organization_id,
            'deployment_id' => (string) $deployment->getKey(),
            'runner_id' => (string) $runner->getKey(),
            'storage_type' => ArtifactStorageType::LOCAL,
            'storage_path' => $artifactPath,
            'checksum' => $checksum,
            'size_bytes' => $sizeBytes,
            'runtime' => $site->runtime->value,
            'created_at' => now(),
        ]);
    }

    public function verify(string $checksum, SSHConnectionInterface $ssh, string $remotePath): void
    {
        $result = $ssh->run(sprintf('sha256sum %s', escapeshellarg($remotePath)))->throw();
        $actual = Sha256OutputParser::parse($result->stdout);

        if ($actual !== $checksum) {
            throw new ArtifactCorruptedException($remotePath, $checksum, $actual);
        }
    }

    public function cleanup(SSHConnectionInterface $ssh, string $path): void
    {
        try {
            $ssh->run(sprintf('rm -f %s', escapeshellarg($path)))->throw();
        } catch (SSHCommandFailedException $exception) {
            Log::warning('Failed to cleanup artifact path on remote host.', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
