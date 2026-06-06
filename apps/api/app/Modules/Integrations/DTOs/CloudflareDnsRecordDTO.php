<?php

declare(strict_types=1);

namespace App\Modules\Integrations\DTOs;

final readonly class CloudflareDnsRecordDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public string $content,
        public bool $proxied,
    ) {
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function fromCloudflare(array $record): self
    {
        return new self(
            id: (string) ($record['id'] ?? ''),
            name: (string) ($record['name'] ?? ''),
            type: (string) ($record['type'] ?? ''),
            content: (string) ($record['content'] ?? ''),
            proxied: (bool) ($record['proxied'] ?? false),
        );
    }
}
