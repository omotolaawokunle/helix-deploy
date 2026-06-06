<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum SslProvider: string
{
    case LETSENCRYPT = 'letsencrypt';
}
