<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

use Illuminate\Http\Request;

readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array{email?: string, password?: string} $validated */
        $validated = method_exists($request, 'validated')
            ? $request->validated()
            : $request->only(['email', 'password']);

        return new self(
            email: (string) ($validated['email'] ?? ''),
            password: (string) ($validated['password'] ?? ''),
        );
    }
}
