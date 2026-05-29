<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

use Illuminate\Http\Request;

readonly class RegisterDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array{name?: string, email?: string, password?: string} $validated */
        $validated = method_exists($request, 'validated')
            ? $request->validated()
            : $request->only(['name', 'email', 'password']);

        return new self(
            name: (string) ($validated['name'] ?? ''),
            email: (string) ($validated['email'] ?? ''),
            password: (string) ($validated['password'] ?? ''),
        );
    }
}
