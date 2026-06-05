<?php

declare(strict_types=1);

namespace App\Packages\SSH\Exceptions;

use App\Modules\BuildRunners\Models\BuildRunner;

class BuildRunnerSSHFingerprintMismatchException extends SSHConnectionException
{
    public function __construct(
        public readonly BuildRunner $runner,
        public readonly string $expectedFingerprint,
        public readonly string $receivedFingerprint,
    ) {
        parent::__construct(sprintf(
            'SSH fingerprint mismatch for build runner %s (%s). Expected [%s], received [%s].',
            (string) $runner->getKey(),
            (string) $runner->name,
            $expectedFingerprint,
            $receivedFingerprint,
        ));
    }
}
