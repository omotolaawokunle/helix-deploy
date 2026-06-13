<?php

declare(strict_types=1);

namespace App\Modules\Servers\Enums;

enum ServerLogType: string
{
    case NGINX_ACCESS = 'nginx_access';
    case NGINX_ERROR = 'nginx_error';
}
