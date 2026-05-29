<?php

declare(strict_types=1);

namespace App\Modules\Servers\Enums;

enum ServerEnvironment: string
{
    case DEVELOPMENT = 'development';
    case STAGING = 'staging';
    case PRODUCTION = 'production';

    public function label(): string
    {
        return match ($this) {
            self::DEVELOPMENT => 'Development',
            self::STAGING => 'Staging',
            self::PRODUCTION => 'Production',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DEVELOPMENT => 'secondary',
            self::STAGING => 'warning',
            self::PRODUCTION => 'danger',
        };
    }
}
