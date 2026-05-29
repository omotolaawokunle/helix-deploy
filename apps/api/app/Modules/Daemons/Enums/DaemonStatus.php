<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Enums;

enum DaemonStatus: string
{
    case RUNNING = 'running';
    case STOPPED = 'stopped';
    case ERROR = 'error';

    public function label(): string
    {
        return match ($this) {
            self::RUNNING => 'Running',
            self::STOPPED => 'Stopped',
            self::ERROR => 'Error',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RUNNING => 'success',
            self::STOPPED => 'neutral',
            self::ERROR => 'danger',
        };
    }
}
