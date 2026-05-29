<?php

declare(strict_types=1);

namespace App\Modules\Credentials\DTOs;

readonly class StoredKeyPair
{
    public function __construct(
        public string $credentialId,
        public string $publicKey,
    ) {
    }
}
