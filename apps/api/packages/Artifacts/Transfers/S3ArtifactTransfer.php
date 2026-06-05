<?php

declare(strict_types=1);

namespace App\Packages\Artifacts\Transfers;

use App\Packages\Artifacts\Contracts\ArtifactTransferInterface;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use RuntimeException;

final class S3ArtifactTransfer implements ArtifactTransferInterface
{
    public function transfer(
        SSHConnectionInterface $runnerSsh,
        SSHConnectionInterface $targetSsh,
        string $runnerPath,
        string $targetPath,
        string $expectedChecksum,
    ): void {
        unset($runnerSsh, $targetSsh, $runnerPath, $targetPath, $expectedChecksum);

        throw new RuntimeException('S3 artifact transfer is not implemented in v1. Use SCP.');
    }
}
