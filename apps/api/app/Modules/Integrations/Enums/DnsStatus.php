<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

enum DnsStatus: string
{
    case NONE = 'none';
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case FAILED = 'failed';
}
