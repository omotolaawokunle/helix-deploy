<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum Runtime: string
{
    case PHP = 'php';
    case NODEJS = 'nodejs';
    case PYTHON = 'python';
    case GO = 'go';
    case STATIC = 'static';
    case DOCKER = 'docker';

    public function label(): string
    {
        return match ($this) {
            self::PHP => 'PHP',
            self::NODEJS => 'Node.js',
            self::PYTHON => 'Python',
            self::GO => 'Go',
            self::STATIC => 'Static',
            self::DOCKER => 'Docker',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PHP => 'info',
            self::NODEJS => 'success',
            self::PYTHON => 'warning',
            self::GO => 'secondary',
            self::STATIC => 'neutral',
            self::DOCKER => 'primary',
        };
    }
}
