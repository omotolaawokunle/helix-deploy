<?php

declare(strict_types=1);

namespace App\Packages\DatabaseBrowser\Validation;

use Illuminate\Validation\ValidationException;

final class SqlIdentifierValidator
{
    private const PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public function assertDatabaseName(string $name): void
    {
        if (! preg_match(self::PATTERN, $name)) {
            throw ValidationException::withMessages([
                'database' => ['Invalid database name.'],
            ]);
        }
    }

    public function assertTableName(string $name): void
    {
        if (! preg_match(self::PATTERN, $name)) {
            throw ValidationException::withMessages([
                'table' => ['Invalid table name.'],
            ]);
        }
    }

    public function assertColumnName(string $name): void
    {
        if (! preg_match(self::PATTERN, $name)) {
            throw ValidationException::withMessages([
                'filter' => ['Invalid filter column name.'],
            ]);
        }
    }
}
