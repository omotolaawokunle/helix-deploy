<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Enums;

enum MysqlVersion: string
{
    case V8_0 = '8.0';
    case V8_4 = '8.4';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::V8_4;
    }
}
