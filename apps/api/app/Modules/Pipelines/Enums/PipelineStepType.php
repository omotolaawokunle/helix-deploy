<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Enums;

enum PipelineStepType: string
{
    case DEPLOY = 'deploy';
    case MIGRATE = 'migrate';
    case HEALTH_CHECK = 'health_check';
    case APPROVE = 'approve';
    case SCRIPT = 'script';
    case NOTIFY = 'notify';

    public function label(): string
    {
        return match ($this) {
            self::DEPLOY => 'Deploy',
            self::MIGRATE => 'Migrate',
            self::HEALTH_CHECK => 'Health Check',
            self::APPROVE => 'Approve',
            self::SCRIPT => 'Script',
            self::NOTIFY => 'Notify',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DEPLOY => 'info',
            self::MIGRATE => 'warning',
            self::HEALTH_CHECK => 'success',
            self::APPROVE => 'secondary',
            self::SCRIPT => 'primary',
            self::NOTIFY => 'neutral',
        };
    }
}
