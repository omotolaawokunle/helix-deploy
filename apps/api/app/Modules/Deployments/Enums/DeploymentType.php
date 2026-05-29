<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Enums;

enum DeploymentType: string
{
    case DEPLOY = 'deploy';
    case ROLLBACK = 'rollback';

    public function label(): string
    {
        return match ($this) {
            self::DEPLOY => 'Deploy',
            self::ROLLBACK => 'Rollback',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DEPLOY => 'info',
            self::ROLLBACK => 'warning',
        };
    }
}
