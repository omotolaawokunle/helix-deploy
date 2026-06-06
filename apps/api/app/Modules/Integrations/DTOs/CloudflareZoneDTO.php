<?php

declare(strict_types=1);

namespace App\Modules\Integrations\DTOs;

readonly class CloudflareZoneDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromCloudflare(array $payload): self
    {
        return new self(
            id: (string) ($payload['id'] ?? ''),
            name: (string) ($payload['name'] ?? ''),
            status: (string) ($payload['status'] ?? ''),
        );
    }
}
