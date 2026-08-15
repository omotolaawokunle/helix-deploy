<?php

declare(strict_types=1);

namespace App\Modules\Sites\DTOs;

final readonly class BitbucketCredential
{
    private function __construct(
        public string $token,
        public ?string $email,
    ) {}

    public static function pack(string $token, ?string $email): string
    {
        if ($email === null || $email === '') {
            return $token;
        }

        return json_encode([
            'email' => $email,
            'token' => $token,
        ], JSON_THROW_ON_ERROR);
    }

    public static function parse(string $stored): self
    {
        $decoded = json_decode($stored, true);

        if (is_array($decoded) && isset($decoded['token']) && is_string($decoded['token']) && $decoded['token'] !== '') {
            $email = $decoded['email'] ?? null;

            return new self(
                token: $decoded['token'],
                email: is_string($email) && $email !== '' ? $email : null,
            );
        }

        return new self(token: $stored, email: null);
    }
}
