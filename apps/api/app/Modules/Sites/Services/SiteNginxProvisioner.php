<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Exceptions\NginxConfigInvalidException;
use App\Modules\Sites\Models\Site;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\SSHManager;

class SiteNginxProvisioner
{
    public function __construct(
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
    ) {
    }

    public function apply(Server $server, Site $site, string $config): void
    {
        $this->withConnection($server, function (SSHConnectionInterface $connection) use ($site, $config): void {
            $availablePath = $this->sitesAvailablePath($site->domain);
            $enabledPath = $this->sitesEnabledPath($site->domain);
            $tmpPath = $this->nginxConfigTempPath($site->domain);

            if (! $connection->upload($config, $tmpPath)) {
                throw new NginxConfigInvalidException($site->domain, 'Failed to upload nginx configuration.');
            }

            $connection->run(sprintf(
                'sudo cp %s %s && sudo ln -sfn %s %s && rm -f %s',
                escapeshellarg($tmpPath),
                escapeshellarg($availablePath),
                escapeshellarg($availablePath),
                escapeshellarg($enabledPath),
                escapeshellarg($tmpPath),
            ))->throw();

            $this->testAndReload($connection, $site->domain);
        });
    }

    public function remove(Server $server, Site $site): void
    {
        $this->withConnection($server, function (SSHConnectionInterface $connection) use ($site): void {
            $availablePath = $this->sitesAvailablePath($site->domain);
            $enabledPath = $this->sitesEnabledPath($site->domain);

            $connection->run(sprintf('sudo rm -f %s', escapeshellarg($enabledPath)))->throw();
            $connection->run(sprintf('sudo rm -f %s', escapeshellarg($availablePath)))->throw();

            $this->testAndReload($connection, $site->domain);
        });
    }

    public function createWebroot(Server $server, string $domain): void
    {
        $basePath = $this->webrootBase($domain);
        $owner = $this->webrootOwner($server);

        $this->withConnection($server, function (SSHConnectionInterface $connection) use ($basePath, $owner): void {
            $this->ensureBootstrapCurrent($connection, $basePath);

            $connection->run(sprintf(
                'sudo chown -R %s %s && sudo chmod 755 %s',
                escapeshellarg($owner.':www-data'),
                escapeshellarg($basePath),
                escapeshellarg($basePath),
            ))->throw();
        });
    }

    /**
     * Capistrano webroot is {base}/current/public. Create a bootstrap release and
     * symlink current → it so HTTP-01 SSL can run before the first deploy.
     * current must be a symlink (never a real directory) so activate-release's ln -sfn works.
     */
    public function ensureBootstrapCurrent(SSHConnectionInterface $connection, string $basePath): void
    {
        $base = rtrim($basePath, '/');
        $bootstrap = $base.'/releases/.helix-bootstrap';
        $current = $base.'/current';

        $connection->run(sprintf(
            'sudo mkdir -p %1$s/releases %1$s/shared %1$s/shared/storage %2$s/public/.well-known/acme-challenge'
            .' && if [ ! -e %3$s ]; then sudo ln -sfn %2$s %3$s;'
            .' elif [ -d %3$s ] && [ ! -L %3$s ]; then sudo rm -rf %3$s && sudo ln -sfn %2$s %3$s; fi',
            escapeshellarg($base),
            escapeshellarg($bootstrap),
            escapeshellarg($current),
        ))->throw();
    }

    public function removeWebroot(Server $server, string $domain): void
    {
        $basePath = $this->webrootBase($domain);

        $this->withConnection($server, function (SSHConnectionInterface $connection) use ($basePath): void {
            $connection->run(sprintf('sudo rm -rf %s', escapeshellarg($basePath)))->throw();
        });
    }

    private function withConnection(Server $server, callable $callback): void
    {
        $connection = $this->sshManager->connect($server, $this->credentialVault)->connect();

        try {
            $callback($connection);
        } finally {
            $connection->disconnect();
        }
    }

    private function testAndReload(SSHConnectionInterface $connection, string $domain): void
    {
        $testResult = $connection->run('sudo nginx -t');

        if ($testResult->failed()) {
            throw new NginxConfigInvalidException($domain, $testResult->output());
        }

        $connection->run('sudo systemctl reload nginx')->throw();
    }

    public function rollbackConfig(Server $server, string $domain): void
    {
        $this->withConnection($server, function (SSHConnectionInterface $connection) use ($domain): void {
            $connection->run(sprintf('sudo rm -f %s', escapeshellarg($this->sitesEnabledPath($domain))))->throw();
            $connection->run(sprintf('sudo rm -f %s', escapeshellarg($this->sitesAvailablePath($domain))))->throw();
        });
    }

    private function sitesAvailablePath(string $domain): string
    {
        return '/etc/nginx/sites-available/'.$domain;
    }

    private function sitesEnabledPath(string $domain): string
    {
        return '/etc/nginx/sites-enabled/'.$domain;
    }

    private function nginxConfigTempPath(string $domain): string
    {
        return '/tmp/helix-nginx-'.$domain.'.conf';
    }

    private function webrootBase(string $domain): string
    {
        return '/var/www/'.$domain;
    }

    private function webrootOwner(Server $server): string
    {
        $sshUser = trim((string) $server->ssh_user);

        return $sshUser !== '' ? $sshUser : 'deploy';
    }
}
