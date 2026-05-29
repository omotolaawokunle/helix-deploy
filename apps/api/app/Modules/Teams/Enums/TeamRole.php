<?php

declare(strict_types=1);

namespace App\Modules\Teams\Enums;

enum TeamRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case DEVELOPER = 'developer';
    case VIEWER = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::ADMIN => 'Admin',
            self::DEVELOPER => 'Developer',
            self::VIEWER => 'Viewer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OWNER => 'danger',
            self::ADMIN => 'warning',
            self::DEVELOPER => 'info',
            self::VIEWER => 'neutral',
        };
    }
}
