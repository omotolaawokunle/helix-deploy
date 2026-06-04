<?php

declare(strict_types=1);

namespace App\Modules\Daemons\DTOs;

readonly class CreateDaemonDTO
{
    public function __construct(
        public string $name,
        public string $command,
        public ?string $directory = null,
        public string $user = 'www-data',
        public int $processes = 1,
    ) {
    }
}
