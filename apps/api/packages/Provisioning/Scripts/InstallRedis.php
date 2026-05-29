<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Models\Organization;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

class InstallRedis extends BaseProvisioningScript
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
        private readonly Organization $organization,
        private readonly ?string $configuredPassword = null,
    ) {
    }

    public function name(): string
    {
        return 'redis';
    }

    public function description(): string
    {
        return 'Installs Redis and secures network defaults.';
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

        $this->runStep($ssh, $this->apt('apt-get update -y'), 'apt-update');
        $this->runStep($ssh, $this->apt('apt-get install -y redis-server'), 'install-redis');
        $this->runStep(
            $ssh,
            "grep -q '^bind 127.0.0.1' /etc/redis/redis.conf || sed -i 's/^bind .*/bind 127.0.0.1/' /etc/redis/redis.conf",
            'configure-redis-bind',
        );

        $redisPassword = $options['redisPassword'] ?? $this->configuredPassword;
        if (is_string($redisPassword) && $redisPassword !== '') {
            $escapedPassword = escapeshellarg($redisPassword);
            $this->runStep(
                $ssh,
                "sed -i '/^requirepass /d' /etc/redis/redis.conf && printf 'requirepass %s\n' {$escapedPassword} >> /etc/redis/redis.conf",
                'configure-redis-password',
            );

            $this->credentialVault->storeSecret(
                organization: $this->organization,
                owner: $server,
                name: sprintf('%s-redis-password', $server->hostname),
                value: $redisPassword,
            );
        }

        $this->runStep($ssh, 'systemctl enable redis-server', 'enable-redis');
        $this->runStep($ssh, 'systemctl restart redis-server', 'restart-redis');
    }
}
