<?php

declare(strict_types=1);

namespace App\Modules\Servers\DTOs;

readonly class ServerServiceCredentialDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $serviceKey,
        public string $label,
        public ?string $createdAt,
    ) {
    }
}
