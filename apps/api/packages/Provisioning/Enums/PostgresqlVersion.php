<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Enums;

enum PostgresqlVersion: string
{
    case V14 = '14';
    case V15 = '15';
    case V16 = '16';
    case V17 = '17';
    case V18 = '18';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::V16;
    }
}
