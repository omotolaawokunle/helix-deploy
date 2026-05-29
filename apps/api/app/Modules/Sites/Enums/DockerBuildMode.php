<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum DockerBuildMode: string
{
    case PULL = 'pull';
    case BUILD = 'build';

    public function label(): string
    {
        return match ($this) {
            self::PULL => 'Pull',
            self::BUILD => 'Build',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PULL => 'info',
            self::BUILD => 'warning',
        };
    }
}
