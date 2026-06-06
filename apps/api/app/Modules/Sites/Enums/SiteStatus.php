<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum SiteStatus: string
{
    case PROVISIONING = 'provisioning';
    case ACTIVE = 'active';
    case DISCOVERED = 'discovered';
    case FAILED = 'failed';
}
