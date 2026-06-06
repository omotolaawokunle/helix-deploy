<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

use App\Modules\Auth\Requests\ChangePasswordRequest;

final readonly class ChangePasswordDTO
{
    public function __construct(
        public string $currentPassword,
        public string $password,
    ) {
    }

    public static function fromRequest(ChangePasswordRequest $request): self
    {
        /** @var array{currentPassword: string, password: string} $validated */
        $validated = $request->validated();

        return new self(
            currentPassword: $validated['currentPassword'],
            password: $validated['password'],
        );
    }
}
