<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Enums;

enum BuildStrategy: string
{
    case ON_SERVER = 'on_server';
    case RUNNER = 'runner';
    case EXTERNAL = 'external';

    public function label(): string
    {
        return match ($this) {
            self::ON_SERVER => 'On server',
            self::RUNNER => 'Build runner',
            self::EXTERNAL => 'External artifact',
        };
    }
}
