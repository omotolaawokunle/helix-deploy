<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum SslStatus: string
{
    case NONE = 'none';
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case FAILED = 'failed';
}
