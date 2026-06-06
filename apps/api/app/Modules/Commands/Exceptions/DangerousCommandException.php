<?php

declare(strict_types=1);

namespace App\Modules\Commands\Exceptions;

use RuntimeException;

final class DangerousCommandException extends RuntimeException
{
    public function __construct(
        public readonly string $blockedPattern,
        string $message = 'This command is blocked for safety reasons.',
    ) {
        parent::__construct($message);
    }
}
