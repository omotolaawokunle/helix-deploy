<?php

declare(strict_types=1);

use App\Modules\Servers\Enums\ServerLogType;
use App\Modules\Servers\Services\ServerLogPathResolver;

it('resolves global nginx log paths', function (): void {
    $resolver = new ServerLogPathResolver();

    expect($resolver->resolve(ServerLogType::NGINX_ACCESS))
        ->toBe('/var/log/nginx/access.log')
        ->and($resolver->resolve(ServerLogType::NGINX_ERROR))
        ->toBe('/var/log/nginx/error.log');
});
