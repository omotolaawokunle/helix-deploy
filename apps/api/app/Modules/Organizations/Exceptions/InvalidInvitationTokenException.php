<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Exceptions;

use RuntimeException;

class InvalidInvitationTokenException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $expired = false,
    ) {
        parent::__construct($message);
    }

    public static function malformed(): self
    {
        return new self('Invalid invitation link.');
    }

    public static function expired(): self
    {
        return new self('Invitation link has expired.', expired: true);
    }
}
