<?php

declare(strict_types=1);

namespace App\Modules\Servers\DTOs;

final readonly class DiscoveredDaemonSnapshot
{
    public function __construct(
        public string $name,
        public string $command,
        public ?string $directory,
        public string $user,
        public int $processes,
        public string $configPath,
        public string $status,
    ) {
    }
}
