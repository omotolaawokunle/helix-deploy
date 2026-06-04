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

            if (! $connection->upload($config, $availablePath)) {
                throw new NginxConfigInvalidException($site->domain, 'Failed to upload nginx configuration.');
            }

            $connection->run(sprintf(
                'ln -sfn %s %s',
                escapeshellarg($availablePath),
                escapeshellarg($enabledPath),
            ))->throw();

            $this->testAndReload($connection, $site->domain);
        });
    }

    public function remove(Server $server, Site $site): void
    {
        $this->withConnection($server, function (SSHConnectionInterface $connection) use ($site): void {
            $availablePath = $this->sitesAvailablePath($site->domain);
            $enabledPath = $this->sitesEnabledPath($site->domain);

            $connection->run(sprintf('rm -f %s', escapeshellarg($enabledPath)));
            $connection->run(sprintf('rm -f %s', escapeshellarg($availablePath)));

            $this->testAndReload($connection, $site->domain);
        });
    }

    public function createWebroot(Server $server, string $domain): void
    {
        $basePath = $this->webrootBase($domain);

        $this->withConnection($server, function (SSHConnectionInterface $connection) use ($basePath): void {
            $connection->run(sprintf(
                'mkdir -p %s/releases %s/shared %s/shared/storage',
                escapeshellarg($basePath),
                escapeshellarg($basePath),
                escapeshellarg($basePath),
            ))->throw();

            $connection->run(sprintf(
                'chown -R deploy:www-data %s && chmod 755 %s',
                escapeshellarg($basePath),
                escapeshellarg($basePath),
            ))->throw();
        });
    }

    public function removeWebroot(Server $server, string $domain): void
    {
        $basePath = $this->webrootBase($domain);

        $this->withConnection($server, function (SSHConnectionInterface $connection) use ($basePath): void {
            $connection->run(sprintf('rm -rf %s', escapeshellarg($basePath)));
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
            $connection->run(sprintf('rm -f %s', escapeshellarg($this->sitesEnabledPath($domain))));
            $connection->run(sprintf('rm -f %s', escapeshellarg($this->sitesAvailablePath($domain))));
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

    private function webrootBase(string $domain): string
    {
        return '/var/www/'.$domain;
    }
}
