<?php

declare(strict_types=1);

namespace App\Packages\DatabaseBrowser\Enums;

enum DatabaseEngine: string
{
    case POSTGRESQL = 'postgresql';
    case MYSQL = 'mysql';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $engine): string => $engine->value, self::cases());
    }
}
