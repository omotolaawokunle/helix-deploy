<?php

declare(strict_types=1);

namespace App\Packages\Artifacts\Exceptions;

use RuntimeException;

final class ArtifactCorruptedException extends RuntimeException
{
    public function __construct(
        public readonly string $remotePath,
        public readonly string $expectedChecksum,
        public readonly string $actualChecksum,
    ) {
        parent::__construct(sprintf(
            'Artifact checksum mismatch at %s: expected %s, got %s',
            $remotePath,
            $expectedChecksum,
            $actualChecksum,
        ));
    }
}
