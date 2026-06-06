<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Enums;

enum PipelineRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case AWAITING_APPROVAL = 'awaiting_approval';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::SUCCESS,
            self::FAILED,
            self::CANCELLED,
        ], true);
    }
}
