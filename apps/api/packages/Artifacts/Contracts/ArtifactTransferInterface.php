<?php

declare(strict_types=1);

namespace App\Packages\Artifacts\Contracts;

use App\Packages\SSH\Contracts\SSHConnectionInterface;

interface ArtifactTransferInterface
{
    public function transfer(
        SSHConnectionInterface $runnerSsh,
        SSHConnectionInterface $targetSsh,
        string $runnerPath,
        string $targetPath,
        string $expectedChecksum,
    ): void;
}
