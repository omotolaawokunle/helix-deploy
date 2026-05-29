<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum NodePM: string
{
    case PM2 = 'pm2';
    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::PM2 => 'PM2',
            self::NONE => 'None',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PM2 => 'success',
            self::NONE => 'neutral',
        };
    }
}
