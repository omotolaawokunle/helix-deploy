<?php

declare(strict_types=1);

namespace App\Packages\SSH\Exceptions;

use App\Modules\Servers\Models\Server;

class SSHFingerprintMismatchException extends SSHConnectionException
{
    public function __construct(
        public readonly Server $server,
        public readonly string $expectedFingerprint,
        public readonly string $receivedFingerprint,
    ) {
        parent::__construct(sprintf(
            'SSH fingerprint mismatch for server %s (%s). Expected [%s], received [%s].',
            (string) $server->getKey(),
            (string) $server->hostname,
            $expectedFingerprint,
            $receivedFingerprint,
        ));
    }
}
