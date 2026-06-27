<?php

declare(strict_types=1);

namespace App\Packages\DatabaseBrowser\DTOs;

use App\Packages\DatabaseBrowser\Enums\DatabaseEngine;

final class DatabaseConnectionConfig
{
    public function __construct(
        public readonly DatabaseEngine $engine,
        public readonly string $host,
        public readonly int $port,
        public readonly string $username,
        public readonly string $password,
        public readonly ?string $database = null,
    ) {
    }
}
