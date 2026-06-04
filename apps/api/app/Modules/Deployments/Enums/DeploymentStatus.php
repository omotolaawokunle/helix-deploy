<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Enums;

enum DeploymentStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case AWAITING_APPROVAL = 'awaiting_approval';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::RUNNING => 'Running',
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::AWAITING_APPROVAL => 'Awaiting Approval',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::SUCCESS,
            self::FAILED,
            self::CANCELLED,
        ], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'secondary',
            self::RUNNING => 'info',
            self::SUCCESS => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'neutral',
            self::AWAITING_APPROVAL => 'warning',
        };
    }
}
