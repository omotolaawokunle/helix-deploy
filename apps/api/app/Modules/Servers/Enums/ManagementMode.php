<?php

declare(strict_types=1);

namespace App\Modules\Servers\Enums;

enum ManagementMode: string
{
    case MANAGED = 'managed';
    case OBSERVE = 'observe';

    public function label(): string
    {
        return match ($this) {
            self::MANAGED => 'Managed',
            self::OBSERVE => 'Observe',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MANAGED => 'success',
            self::OBSERVE => 'warning',
        };
    }
}
