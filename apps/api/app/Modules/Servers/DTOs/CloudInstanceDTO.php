<?php

declare(strict_types=1);

namespace App\Modules\Servers\DTOs;

readonly class CloudInstanceDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $ipAddress,
        public ?string $region,
        public ?string $serverType,
        public string $status,
        public ?string $os,
    ) {
    }
}
