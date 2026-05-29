<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum DeployMode: string
{
    case GIT = 'git';
    case DOCKER = 'docker';

    public function label(): string
    {
        return match ($this) {
            self::GIT => 'Git',
            self::DOCKER => 'Docker',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::GIT => 'info',
            self::DOCKER => 'primary',
        };
    }
}
