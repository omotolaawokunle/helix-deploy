<?php

declare(strict_types=1);

namespace App\Packages\Encryption\Contracts;

use App\Packages\Encryption\EncryptedPayload;

interface EncryptionInterface
{
    public function encrypt(string $plaintext, string $key): EncryptedPayload;

    public function decrypt(EncryptedPayload $payload, string $key): string;

    public function generateKey(): string;
}
