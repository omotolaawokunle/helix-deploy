<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum SiteLogType: string
{
    case NGINX_ACCESS = 'nginx_access';
    case NGINX_ERROR = 'nginx_error';
    case APPLICATION = 'application';
}
