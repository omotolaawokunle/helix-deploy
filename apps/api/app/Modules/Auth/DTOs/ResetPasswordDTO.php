<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

use App\Modules\Auth\Requests\ResetPasswordRequest;

final readonly class ResetPasswordDTO
{
    public function __construct(
        public string $email,
        public string $token,
        public string $password,
    ) {
    }

    public static function fromRequest(ResetPasswordRequest $request): self
    {
        /** @var array{email: string, token: string, password: string} $validated */
        $validated = $request->validated();

        return new self(
            email: $validated['email'],
            token: $validated['token'],
            password: $validated['password'],
        );
    }
}
