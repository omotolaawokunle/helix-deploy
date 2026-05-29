<?php

declare(strict_types=1);

namespace App\Modules\Servers\Enums;

enum ServerStatus: string
{
    case CONNECTING = 'connecting';
    case ACTIVE = 'active';
    case DISCONNECTED = 'disconnected';
    case MAINTENANCE = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::CONNECTING => 'Connecting',
            self::ACTIVE => 'Active',
            self::DISCONNECTED => 'Disconnected',
            self::MAINTENANCE => 'Maintenance',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CONNECTING => 'warning',
            self::ACTIVE => 'success',
            self::DISCONNECTED => 'danger',
            self::MAINTENANCE => 'neutral',
        };
    }
}
