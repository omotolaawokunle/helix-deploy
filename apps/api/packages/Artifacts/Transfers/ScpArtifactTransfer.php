<?php

declare(strict_types=1);

namespace App\Packages\Artifacts\Transfers;

use App\Modules\Servers\Models\Server;
use App\Packages\Artifacts\Contracts\ArtifactTransferInterface;
use App\Packages\Artifacts\Exceptions\ArtifactCorruptedException;
use App\Packages\Artifacts\Exceptions\ArtifactTransferFailedException;
use App\Packages\Artifacts\Support\Sha256OutputParser;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

final class ScpArtifactTransfer implements ArtifactTransferInterface
{
    public function __construct(
        private readonly Server $targetServer,
    ) {}

    public function transfer(
        SSHConnectionInterface $runnerSsh,
        SSHConnectionInterface $targetSsh,
        string $runnerPath,
        string $targetPath,
        string $expectedChecksum,
    ): void {
        $quotedRunnerPath = escapeshellarg($runnerPath);
        $quotedTargetPath = escapeshellarg($targetPath);

        $runnerSsh->run(sprintf('test -f %s', $quotedRunnerPath))->throw();

        $scpCommand = sprintf(
            'scp -o StrictHostKeyChecking=no -P %d %s %s@%s:%s',
            (int) $this->targetServer->ssh_port,
            $quotedRunnerPath,
            (string) $this->targetServer->ssh_user,
            (string) $this->targetServer->ip_address,
            $targetPath,
        );

        $transferResult = $runnerSsh->run($scpCommand);

        if ($transferResult->failed()) {
            throw new ArtifactTransferFailedException(
                'SCP artifact transfer from build runner to target server failed.',
            );
        }

        $checksumResult = $targetSsh->run(sprintf('sha256sum %s', $quotedTargetPath))->throw();
        $actualChecksum = Sha256OutputParser::parse($checksumResult->stdout);

        if ($actualChecksum !== $expectedChecksum) {
            throw new ArtifactCorruptedException($targetPath, $expectedChecksum, $actualChecksum);
        }
    }
}
