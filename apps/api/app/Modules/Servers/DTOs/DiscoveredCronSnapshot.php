<?php

declare(strict_types=1);

namespace App\Modules\Servers\DTOs;

final readonly class DiscoveredCronSnapshot
{
    public function __construct(
        public string $expression,
        public string $command,
        public string $user,
    ) {
    }
}
