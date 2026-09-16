<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

use App\Modules\Auth\Requests\ForgotPasswordRequest;

final readonly class ForgotPasswordDTO
{
    public function __construct(
        public string $email,
    ) {
    }

    public static function fromRequest(ForgotPasswordRequest $request): self
    {
        /** @var array{email: string} $validated */
        $validated = $request->validated();

        return new self(
            email: $validated['email'],
        );
    }
}
