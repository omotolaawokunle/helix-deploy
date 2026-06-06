<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Enums;

enum BuildRunnerStatus: string
{
    case CONNECTING = 'connecting';
    case ONLINE = 'online';
    case OFFLINE = 'offline';
    case MAINTENANCE = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::CONNECTING => 'Connecting',
            self::ONLINE => 'Online',
            self::OFFLINE => 'Offline',
            self::MAINTENANCE => 'Maintenance',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CONNECTING => 'info',
            self::ONLINE => 'success',
            self::OFFLINE => 'danger',
            self::MAINTENANCE => 'warning',
        };
    }
}
