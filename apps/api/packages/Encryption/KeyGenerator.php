<?php

declare(strict_types=1);

namespace App\Packages\Encryption;

final class KeyGenerator
{
    public function generate(): string
    {
        return base64_encode(sodium_crypto_secretbox_keygen());
    }
}
