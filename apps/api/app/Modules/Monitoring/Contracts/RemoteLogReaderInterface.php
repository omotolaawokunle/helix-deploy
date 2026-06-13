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
}
