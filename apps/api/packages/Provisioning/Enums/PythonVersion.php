<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Enums;

enum PythonVersion: string
{
    case V3_10 = '3.10';
    case V3_11 = '3.11';
    case V3_12 = '3.12';
    case V3_13 = '3.13';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::V3_12;
    }
}
