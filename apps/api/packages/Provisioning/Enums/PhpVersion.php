<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Enums;

enum PhpVersion: string
{
    case V8_1 = '8.1';
    case V8_2 = '8.2';
    case V8_3 = '8.3';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
