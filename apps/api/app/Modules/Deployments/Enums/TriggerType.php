<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Enums;

enum TriggerType: string
{
    case MANUAL = 'manual';
    case WEBHOOK = 'webhook';
    case API = 'api';
    case PIPELINE = 'pipeline';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::WEBHOOK => 'Webhook',
            self::API => 'API',
            self::PIPELINE => 'Pipeline',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MANUAL => 'secondary',
            self::WEBHOOK => 'info',
            self::API => 'success',
            self::PIPELINE => 'warning',
        };
    }
}
