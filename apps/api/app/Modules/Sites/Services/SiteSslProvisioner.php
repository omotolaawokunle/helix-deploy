<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Integrations\Enums\DnsProvider;
use App\Modules\Integrations\Services\DnsProviderRegistry;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Contracts\NginxConfigGeneratorInterface;
use App\Modules\Sites\Contracts\SiteSslProvisionerInterface;
use App\Modules\Integrations\Events\SiteDnsSslStatusChanged;
use App\Modules\Sites\Enums\SslChallenge;
use App\Modules\Sites\Enums\SslProvider;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Exceptions\SiteSslException;
use App\Modules\Sites\Models\Site;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\SSHManager;
use Illuminate\Support\Str;

final class SiteSslProvisioner implements SiteSslProvisionerInterface
{
    public function __construct(
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
        private readonly NginxConfigGeneratorInterface $nginxConfigGenerator,
        private readonly SiteNginxProvisioner $siteNginxProvisioner,
        private readonly DnsProviderRegistry $dnsProviderRegistry,
    ) {
    }

    public function issue(Site $site): void
    {
        if (! $site->enable_ssl) {
            return;
        }

        $server = $site->server;

        if ($server === null) {
            $this->markFailed($site, 'Server is not available for SSL issuance.');

            return;
        }

        $challenge = $this->resolveChallenge($site);

        try {
            $this->withConnection($server, function (SSHConnectionInterface $connection) use ($site, $challenge): void {
                $this->ensureCertbotInstalled($connection);

                if ($challenge === SslChallenge::DNS_01) {
                    $this->issueViaDns01($connection, $site);
                } else {
                    $this->issueViaWebroot($connection, $site);
                }
            });

            $site->forceFill([
                'ssl_status' => SslStatus::ACTIVE->value,
                'ssl_provider' => SslProvider::LETSENCRYPT->value,
                'ssl_challenge' => $challenge->value,
                'ssl_error' => null,
            ])->save();

            event(new SiteDnsSslStatusChanged($site->refresh()));

            $config = $this->nginxConfigGenerator->generate($site->refresh());
            $this->siteNginxProvisioner->apply($server, $site, $config);

            AuditLog::record(
                operation: 'site.ssl_certificate.issued',
                resource: $site,
                afterState: [
                    'domain' => $site->domain,
                    'challenge' => $challenge->value,
                    'provider' => SslProvider::LETSENCRYPT->value,
                ],
            );
        } catch (\Throwable $exception) {
            $this->markFailed($site, $exception->getMessage());

            AuditLog::record(
                operation: 'site.ssl_certificate.failed',
                resource: $site,
                afterState: [
                    'domain' => $site->domain,
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }

    public function revoke(Site $site): void
    {
        if ($site->ssl_status !== SslStatus::ACTIVE && $site->ssl_status !== SslStatus::FAILED) {
            return;
        }

        $server = $site->server;

        if ($server === null) {
            return;
        }

        $this->withConnection($server, function (SSHConnectionInterface $connection) use ($site): void {
            $connection->run(sprintf(
                'sudo certbot delete --cert-name %s --non-interactive 2>/dev/null || true',
                escapeshellarg($site->domain),
            ));
        });
    }

    public function renew(Site $site): void
    {
        if ($site->ssl_status !== SslStatus::ACTIVE) {
            return;
        }

        $server = $site->server;

        if ($server === null) {
            return;
        }

        $this->withConnection($server, function (SSHConnectionInterface $connection) use ($site): void {
            $this->ensureCertbotInstalled($connection);

            $connection->run(sprintf(
                'sudo certbot renew --cert-name %s --non-interactive',
                escapeshellarg($site->domain),
            ))->throw();
        });

        AuditLog::record(
            operation: 'site.ssl_certificate.renewed',
            resource: $site,
            afterState: ['domain' => $site->domain],
        );
    }

    private function issueViaWebroot(SSHConnectionInterface $connection, Site $site): void
    {
        $connection->run($this->buildCertbotWebrootCommand($site))->throw();
    }

    private function issueViaDns01(SSHConnectionInterface $connection, Site $site): void
    {
        $provider = $this->resolveDnsProviderForSsl($site);

        match ($provider) {
            DnsProvider::CLOUDFLARE => $this->issueViaDnsCloudflare($connection, $site, $provider),
            DnsProvider::DIGITALOCEAN => $this->issueViaDnsDigitalOcean($connection, $site, $provider),
        };
    }

    private function issueViaDnsCloudflare(
        SSHConnectionInterface $connection,
        Site $site,
        DnsProvider $provider,
    ): void {
        $this->issueViaDnsPlugin(
            connection: $connection,
            site: $site,
            provider: $provider,
            credentialsPathPrefix: 'helix-cf-',
            credentialsContent: fn (string $token): string => 'dns_cloudflare_api_token = '.$token,
            certbotCommandBuilder: fn (Site $siteModel, string $credentialsPath): string => $this->buildCertbotDnsCloudflareCommand($siteModel, $credentialsPath),
        );
    }

    private function issueViaDnsDigitalOcean(
        SSHConnectionInterface $connection,
        Site $site,
        DnsProvider $provider,
    ): void {
        $this->issueViaDnsPlugin(
            connection: $connection,
            site: $site,
            provider: $provider,
            credentialsPathPrefix: 'helix-do-',
            credentialsContent: fn (string $token): string => 'dns_digitalocean_token = '.$token,
            certbotCommandBuilder: fn (Site $siteModel, string $credentialsPath): string => $this->buildCertbotDnsDigitalOceanCommand($siteModel, $credentialsPath),
        );
    }

    /**
     * @param callable(string): string $credentialsContent
     * @param callable(Site, string): string $certbotCommandBuilder
     */
    private function issueViaDnsPlugin(
        SSHConnectionInterface $connection,
        Site $site,
        DnsProvider $provider,
        string $credentialsPathPrefix,
        callable $credentialsContent,
        callable $certbotCommandBuilder,
    ): void {
        $organization = $site->organization;
        abort_if($organization === null, 404);

        if (! $this->dnsProviderRegistry->isConnected($organization, $provider)) {
            throw new SiteSslException(sprintf(
                '%s must be connected to use DNS-01 validation.',
                $provider->label(),
            ));
        }

        $token = $this->dnsProviderRegistry->getCredential($organization, $provider);
        $credentialsPath = '/tmp/'.$credentialsPathPrefix.Str::uuid()->toString().'.ini';

        try {
            if (! $connection->upload($credentialsContent($token), $credentialsPath)) {
                throw new SiteSslException(sprintf(
                    'Failed to upload %s credentials for certbot.',
                    $provider->label(),
                ));
            }

            $connection->run(sprintf('chmod 600 %s', escapeshellarg($credentialsPath)))->throw();
            $connection->run($certbotCommandBuilder($site, $credentialsPath))->throw();
        } finally {
            sodium_memzero($token);
            $connection->run(sprintf('rm -f %s', escapeshellarg($credentialsPath)));
        }
    }

    private function buildCertbotDnsDigitalOceanCommand(Site $site, string $credentialsPath): string
    {
        $domains = $this->domainFlags($site);

        return sprintf(
            'sudo certbot certonly --dns-digitalocean --dns-digitalocean-credentials %s %s --non-interactive --agree-tos --email %s --no-eff-email',
            escapeshellarg($credentialsPath),
            $domains,
            escapeshellarg($this->certificateEmail($site)),
        );
    }

    private function resolveDnsProviderForSsl(Site $site): DnsProvider
    {
        $provider = $site->dns_provider;

        if ($provider instanceof DnsProvider) {
            return $provider;
        }

        if (is_string($provider) && $provider !== '') {
            $resolved = DnsProvider::tryFrom($provider);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        $projectDnsZone = $site->projectDnsZone;

        if ($projectDnsZone !== null) {
            $zoneProvider = $projectDnsZone->dns_provider;

            if ($zoneProvider instanceof DnsProvider) {
                return $zoneProvider;
            }

            if (is_string($zoneProvider) && $zoneProvider !== '') {
                $resolved = DnsProvider::tryFrom($zoneProvider);

                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        throw new SiteSslException(
            'DNS-01 validation requires a DNS provider. Enable auto-create DNS or select a project DNS zone.',
        );
    }

    private function buildCertbotWebrootCommand(Site $site): string
    {
        $domains = $this->domainFlags($site);

        return sprintf(
            'sudo certbot certonly --webroot -w %s %s --non-interactive --agree-tos --email %s --no-eff-email',
            escapeshellarg($site->webroot),
            $domains,
            escapeshellarg($this->certificateEmail($site)),
        );
    }

    private function buildCertbotDnsCloudflareCommand(Site $site, string $credentialsPath): string
    {
        $domains = $this->domainFlags($site);

        return sprintf(
            'sudo certbot certonly --dns-cloudflare --dns-cloudflare-credentials %s %s --non-interactive --agree-tos --email %s --no-eff-email',
            escapeshellarg($credentialsPath),
            $domains,
            escapeshellarg($this->certificateEmail($site)),
        );
    }

    private function domainFlags(Site $site): string
    {
        $flags = ['-d '.escapeshellarg($site->domain)];

        foreach ($site->aliases ?? [] as $alias) {
            if (is_string($alias) && $alias !== '') {
                $flags[] = '-d '.escapeshellarg($alias);
            }
        }

        return implode(' ', $flags);
    }

    private function certificateEmail(Site $site): string
    {
        return 'ssl@'.$site->domain;
    }

    private function resolveChallenge(Site $site): SslChallenge
    {
        $stored = $site->ssl_challenge;

        if ($stored instanceof SslChallenge) {
            return $stored;
        }

        if (is_string($stored) && $stored !== '') {
            $challenge = SslChallenge::tryFrom($stored);

            if ($challenge !== null) {
                return $challenge;
            }
        }

        return SslChallenge::HTTP_01;
    }

    private function markFailed(Site $site, string $message): void
    {
        $site->forceFill([
            'ssl_status' => SslStatus::FAILED->value,
            'ssl_error' => $message,
        ])->save();

        event(new SiteDnsSslStatusChanged($site->refresh()));
    }

    private function ensureCertbotInstalled(SSHConnectionInterface $connection): void
    {
        $result = $connection->run('command -v certbot >/dev/null 2>&1 && echo yes || echo no');

        if (trim($result->stdout) !== 'yes') {
            throw new SiteSslException(
                'Certbot is not installed on this server. Add the certbot provisioning script or install certbot manually.',
            );
        }
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
}
