<?php

declare(strict_types=1);

namespace App\Packages\Encryption\Exceptions;

use RuntimeException;

class InvalidKeyException extends RuntimeException
{
    public static function forSecretBox(int $expected, int $actual): self
    {
        return new self(sprintf(
            'Invalid key length provided for sodium secretbox. Expected %d bytes, received %d bytes.',
            $expected,
            $actual,
        ));
    }
}
