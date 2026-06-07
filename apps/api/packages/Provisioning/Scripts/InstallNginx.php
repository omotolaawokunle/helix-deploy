<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

class InstallNginx extends BaseProvisioningScript
{
    public function name(): string
    {
        return 'nginx';
    }

    public function description(): string
    {
        return 'Installs and configures Nginx with optimized defaults.';
    }

    public function estimatedMinutes(): int
    {
        return 2;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function handle(SSHConnectionInterface $ssh, Server $server, array $options = []): void
    {
        $this->prepare($options);

        $nginxInstalled = $this->commandExists($ssh, 'nginx');

        if (! $nginxInstalled) {
            $this->runStep($ssh, $this->apt('apt-get update -y'), 'apt-update');
            $this->runStep($ssh, $this->apt('apt-get install -y nginx'), 'install-nginx');
            $this->runStep(
                $ssh,
                <<<'SHELL'
cat <<'EOF' > /etc/nginx/nginx.conf
user www-data;
worker_processes auto;
pid /run/nginx.pid;
include /etc/nginx/modules-enabled/*.conf;
events {
    worker_connections 1024;
}
http {
    sendfile on;
    tcp_nopush on;
    types_hash_max_size 2048;
    server_tokens off;
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    gzip on;
    gzip_comp_level 5;
    gzip_min_length 256;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;
    keepalive_timeout 65;
    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
EOF
SHELL,
                'write-nginx-conf',
            );
        } else {
            $this->logInfo($options, 'nginx already installed — preserving existing configuration');
        }

        $this->preventApachePortConflict($ssh);
        $this->runStep($ssh, 'systemctl enable --now nginx', 'enable-nginx');
        $this->runStep($ssh, 'nginx -t', 'validate-nginx-conf');
        $this->runStep($ssh, 'systemctl reload nginx', 'reload-nginx');
    }
}
