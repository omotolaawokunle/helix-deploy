<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Contracts;

use App\Packages\SSH\Contracts\SSHConnectionInterface;

interface RemoteLogReaderInterface
{
    /**
     * @return list<string>
     */
    public function tail(SSHConnectionInterface $ssh, string $absolutePath, int $lines): array;

    /**
     * @return list<string>
     */
    public function tailLatestMatching(
        SSHConnectionInterface $ssh,
        string $directory,
        string $filenamePattern,
        int $lines,
    ): array;

    /**
     * @param list<string> $directories
     * @return list<string>
     */
    public function tailLatestFromDirectories(
        SSHConnectionInterface $ssh,
        array $directories,
        string $filenamePattern,
        int $lines,
    ): array;

    /**
     * @param list<string> $absolutePaths
     * @return list<string>
     */
    public function tailFirstExisting(
        SSHConnectionInterface $ssh,
        array $absolutePaths,
        int $lines,
    ): array;
}
