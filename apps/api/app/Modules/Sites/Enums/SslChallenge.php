<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum SslChallenge: string
{
    case HTTP_01 = 'http-01';
    case DNS_01 = 'dns-01';
}
