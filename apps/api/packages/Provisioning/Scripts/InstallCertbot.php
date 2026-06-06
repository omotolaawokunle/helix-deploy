<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

class InstallCertbot extends BaseProvisioningScript
{
    public function name(): string
    {
        return 'certbot';
    }

    public function description(): string
    {
        return 'Installs Certbot with Cloudflare and DigitalOcean DNS plugins for Let\'s Encrypt certificates.';
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

        $check = $ssh->run('command -v certbot >/dev/null 2>&1 && echo yes || echo no');

        if (trim($check->stdout) === 'yes') {
            return;
        }

        $this->runStep($ssh, $this->apt('apt-get update -y'), 'apt-update');
        $this->runStep(
            $ssh,
            $this->apt('apt-get install -y certbot python3-certbot-dns-cloudflare python3-certbot-dns-digitalocean'),
            'install-certbot',
        );
    }
}
