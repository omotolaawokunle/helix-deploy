<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Enums;

enum DeploymentStatus: string
{
    case PENDING = 'pending';
    case BUILDING = 'building';
    case BUILT = 'built';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case AWAITING_APPROVAL = 'awaiting_approval';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::BUILDING => 'Building',
            self::BUILT => 'Built',
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

    public function isInProgress(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::BUILDING,
            self::BUILT,
            self::RUNNING,
            self::AWAITING_APPROVAL,
        ], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'secondary',
            self::BUILDING => 'info',
            self::BUILT => 'info',
            self::RUNNING => 'info',
            self::SUCCESS => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'neutral',
            self::AWAITING_APPROVAL => 'warning',
        };
    }
}
