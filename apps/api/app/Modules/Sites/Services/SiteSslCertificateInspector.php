<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Sites\Contracts\SiteSslCertificateInspectorInterface;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Models\Site;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Carbon;

final class SiteSslCertificateInspector implements SiteSslCertificateInspectorInterface
{
    public function inspect(Site $site, SSHConnectionInterface $connection): ?Carbon
    {
        if ($site->ssl_status !== SslStatus::ACTIVE) {
            return null;
        }

        return $this->findCertificateExpiry($site->domain, $connection);
    }

    public function findCertificateExpiry(string $domain, SSHConnectionInterface $connection): ?Carbon
    {
        $expiry = $this->readExpiryViaCertbot($domain, $connection);

        if ($expiry instanceof Carbon) {
            return $expiry;
        }

        $certPath = $this->certificatePath($domain).'/cert.pem';

        foreach ([true, false] as $useSudo) {
            $expiry = $this->readExpiryViaOpenssl($certPath, $connection, $useSudo);

            if ($expiry instanceof Carbon) {
                return $expiry;
            }
        }

        return null;
    }

    public function certificatePath(string $domain): string
    {
        return '/etc/letsencrypt/live/'.$domain;
    }

    public function parseOpenSslEndDate(string $output): ?Carbon
    {
        $trimmed = trim($output);

        if ($trimmed === '') {
            return null;
        }

        if (! preg_match('/^notAfter=(.+)$/m', $trimmed, $matches)) {
            return null;
        }

        try {
            return Carbon::parse(trim($matches[1]));
        } catch (\Throwable) {
            return null;
        }
    }

    public function parseCertbotCertificatesOutput(string $output, string $domain): ?Carbon
    {
        $blocks = preg_split('/\R\s*Certificate Name:\s*/', $output) ?: [];

        foreach ($blocks as $block) {
            if ($block === '') {
                continue;
            }

            if (! $this->certbotBlockMatchesDomain($block, $domain)) {
                continue;
            }

            if (! preg_match('/Expiry Date:\s*(.+?)\s+\((?:VALID|INVALID):/i', $block, $matches)) {
                continue;
            }

            try {
                return Carbon::parse(trim($matches[1]));
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function readExpiryViaCertbot(string $domain, SSHConnectionInterface $connection): ?Carbon
    {
        foreach ([true, false] as $useSudo) {
            $prefix = $useSudo ? 'sudo -n ' : '';
            $result = $connection->run($prefix . 'certbot certificates 2>/dev/null');
            if ($this->isCommandFailure($result)) {
                continue;
            }

            $expiry = $this->parseCertbotCertificatesOutput($result->stdout, $domain);

            if ($expiry instanceof Carbon) {
                return $expiry;
            }
        }

        return null;
    }

    private function readExpiryViaOpenssl(
        string $certPath,
        SSHConnectionInterface $connection,
        bool $useSudo,
    ): ?Carbon {
        $prefix = $useSudo ? 'sudo -n ' : '';
        $result = $connection->run(sprintf(
            '%sopenssl x509 -enddate -noout -in %s 2>/dev/null',
            $prefix,
            escapeshellarg($certPath),
        ));

        if ($this->isCommandFailure($result)) {
            return null;
        }

        return $this->parseOpenSslEndDate($result->stdout);
    }

    private function certbotBlockMatchesDomain(string $block, string $domain): bool
    {
        if (preg_match('/^'.preg_quote($domain, '/').'\s*\R/m', $block) === 1) {
            return true;
        }

        return preg_match('/\bDomains:\s.*\b'.preg_quote($domain, '/').'\b/m', $block) === 1;
    }

    private function isCommandFailure(SSHResult $result): bool
    {
        if ($result->exitCode !== 0) {
            return true;
        }

        $combined = $result->stdout.' '.$result->stderr;

        return str_contains($combined, 'a password is required')
            || str_contains($combined, 'a terminal is required')
            || str_contains($combined, 'sorry, a password is required');
    }
}
