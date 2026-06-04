<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Sites\Contracts\NginxConfigGeneratorInterface;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Models\Site;
use InvalidArgumentException;

class NginxConfigGenerator implements NginxConfigGeneratorInterface
{
    public function generate(Site $site): string
    {
        $serverNames = $this->serverNames($site);
        $webroot = $site->webroot;
        $domain = $site->domain;

        return match ($site->runtime) {
            Runtime::PHP => $this->phpConfig($serverNames, $webroot, $domain, $site->php_version),
            Runtime::NODEJS, Runtime::PYTHON, Runtime::GO, Runtime::DOCKER => $this->proxyConfig(
                $serverNames,
                $domain,
                $this->resolveAppPort($site),
            ),
            Runtime::STATIC => $this->staticConfig($serverNames, $webroot, $domain),
        };
    }

    /**
     * @return list<string>
     */
    private function serverNames(Site $site): string
    {
        $names = array_filter([$site->domain, ...($site->aliases ?? [])]);

        return implode(' ', $names);
    }

    private function phpConfig(string $serverNames, string $webroot, string $domain, ?string $phpVersion): string
    {
        $version = $phpVersion ?? '8.3';
        $socket = '/var/run/php/php'.$version.'-fpm.sock';

        return <<<NGINX
server {
    listen 80;
    server_name {$serverNames};
    root {$webroot};
    index index.php index.html;
    client_max_body_size 100M;
    gzip on;
    gzip_types text/css application/javascript application/json;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:{$socket};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/{$domain}-access.log;
    error_log  /var/log/nginx/{$domain}-error.log;
}
NGINX;
    }

    private function proxyConfig(string $serverNames, string $domain, int $port): string
    {
        return <<<NGINX
server {
    listen 80;
    server_name {$serverNames};
    client_max_body_size 100M;
    gzip on;
    gzip_types text/css application/javascript application/json;

    location / {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/{$domain}-access.log;
    error_log  /var/log/nginx/{$domain}-error.log;
}
NGINX;
    }

    private function staticConfig(string $serverNames, string $webroot, string $domain): string
    {
        return <<<NGINX
server {
    listen 80;
    server_name {$serverNames};
    root {$webroot};
    index index.html;
    client_max_body_size 100M;
    gzip on;
    gzip_types text/css application/javascript application/json;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/{$domain}-access.log;
    error_log  /var/log/nginx/{$domain}-error.log;
}
NGINX;
    }

    private function resolveAppPort(Site $site): int
    {
        if ($site->app_port !== null && $site->app_port > 0) {
            return (int) $site->app_port;
        }

        throw new InvalidArgumentException(sprintf(
            'Site [%s] requires app_port for runtime [%s].',
            (string) $site->getKey(),
            $site->runtime->value,
        ));
    }
}
