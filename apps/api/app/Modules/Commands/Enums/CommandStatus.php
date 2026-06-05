<?php

declare(strict_types=1);

namespace App\Modules\Commands\Enums;

enum CommandStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::FAILED], true);
    }
}
