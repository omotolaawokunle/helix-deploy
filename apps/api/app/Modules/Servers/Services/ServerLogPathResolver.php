<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services;

use App\Modules\Servers\Enums\ServerLogType;

final class ServerLogPathResolver
{
    public function resolve(ServerLogType $type): string
    {
        return match ($type) {
            ServerLogType::NGINX_ACCESS => '/var/log/nginx/access.log',
            ServerLogType::NGINX_ERROR => '/var/log/nginx/error.log',
        };
    }
}
