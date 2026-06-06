<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

use App\Modules\Auth\Requests\UpdateProfileRequest;

final readonly class UpdateProfileDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $timezone,
    ) {
    }

    public static function fromRequest(UpdateProfileRequest $request): self
    {
        /** @var array{name: string, email: string, timezone: string} $validated */
        $validated = $request->validated();

        return new self(
            name: $validated['name'],
            email: $validated['email'],
            timezone: $validated['timezone'],
        );
    }
}
