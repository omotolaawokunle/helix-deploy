<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Enums;

enum NodejsVersion: int
{
    case V18 = 18;
    case V20 = 20;
    case V22 = 22;
    case V24 = 24;

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
