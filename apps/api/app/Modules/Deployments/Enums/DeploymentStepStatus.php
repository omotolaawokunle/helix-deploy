<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Enums;

enum DeploymentStepStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::RUNNING => 'Running',
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
            self::SKIPPED => 'Skipped',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'secondary',
            self::RUNNING => 'info',
            self::SUCCESS => 'success',
            self::FAILED => 'danger',
            self::SKIPPED => 'neutral',
        };
    }
}
