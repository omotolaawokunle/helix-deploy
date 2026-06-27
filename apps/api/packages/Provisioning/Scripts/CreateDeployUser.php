<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Scripts;

use App\Models\Organization;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use InvalidArgumentException;

class CreateDeployUser extends BaseProvisioningScript
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
        private readonly Organization $organization,
    ) {
    }

    public function name(): string
    {
        return 'create-deploy-user';
    }

    public function description(): string
    {
        return 'Creates deploy user, SSH access, and sudo policies.';
    }

    public function estimatedMinutes(): int
    {
        return 1;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function handle(SSHConnectionInterface $ssh, Server $server, array $options = []): void
    {
        $this->prepare($options);

        if (! is_string($server->credential_id) || $server->credential_id === '') {
            throw new InvalidArgumentException('Server credential is required to create deploy user authorized keys.');
        }

        $publicKey = $this->credentialVault->getPublicKey($server->credential_id, $this->organization);

        $this->runStep($ssh, 'id -u deploy &>/dev/null || useradd -m -s /bin/bash deploy', 'ensure-deploy-user');
        $this->runStep($ssh, 'usermod -aG www-data deploy', 'add-deploy-group');
        $this->runStep($ssh, 'mkdir -p /home/deploy/.ssh && chmod 700 /home/deploy/.ssh', 'prepare-ssh-dir');
        $this->runStep(
            $ssh,
            sprintf('printf "%%s\n" %s > /home/deploy/.ssh/authorized_keys', escapeshellarg($publicKey)),
            'write-authorized-keys',
        );
        $this->runStep(
            $ssh,
            'chmod 600 /home/deploy/.ssh/authorized_keys && chown -R deploy:deploy /home/deploy/.ssh',
            'secure-authorized-keys',
        );
        $this->runStep(
            $ssh,
            <<<'SHELL'
cat <<'EOF' > /etc/sudoers.d/deploy
deploy ALL=(ALL) NOPASSWD: /bin/systemctl reload php*-fpm
deploy ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx
deploy ALL=(ALL) NOPASSWD: /bin/systemctl restart nginx
deploy ALL=(ALL) NOPASSWD: /bin/systemctl restart supervisor
deploy ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl *
deploy ALL=(ALL) NOPASSWD: /bin/systemctl restart *
deploy ALL=(ALL) NOPASSWD: /usr/bin/certbot
deploy ALL=(ALL) NOPASSWD: /usr/bin/certbot *
EOF
SHELL,
            'write-sudoers',
        );
        $this->runStep($ssh, 'chmod 440 /etc/sudoers.d/deploy', 'secure-sudoers');
    }
}
