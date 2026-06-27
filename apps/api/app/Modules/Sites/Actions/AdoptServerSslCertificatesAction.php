<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Integrations\Events\SiteDnsSslStatusChanged;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Contracts\SiteSslCertificateInspectorInterface;
use App\Modules\Sites\Enums\SslChallenge;
use App\Modules\Sites\Enums\SslProvider;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Models\Site;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\SSHManager;
use Illuminate\Support\Carbon;

final class AdoptServerSslCertificatesAction
{
    public function __construct(
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
        private readonly SiteSslCertificateInspectorInterface $certificateInspector,
    ) {
    }

    /**
     * @return array{adoptedCount: int, skippedCount: int}
     */
    public function execute(Server $server): array
    {
        if (! $server->isManaged()) {
            return ['adoptedCount' => 0, 'skippedCount' => 0];
        }

        $sites = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $server->getKey())
            ->orderBy('domain')
            ->get();

        if ($sites->isEmpty()) {
            return ['adoptedCount' => 0, 'skippedCount' => 0];
        }

        $connection = $this->sshManager->connect($server, $this->credentialVault)->connect();
        $adoptedCount = 0;
        $skippedCount = 0;

        try {
            foreach ($sites as $site) {
                if (! $site instanceof Site) {
                    continue;
                }

                if ($this->adoptSiteIfCertificateExists($site, $connection)) {
                    $adoptedCount++;
                    continue;
                }

                $skippedCount++;
            }
        } finally {
            $connection->disconnect();
        }

        if ($adoptedCount > 0) {
            AuditLog::record(
                operation: 'server.ssl_certificates.adopted',
                resource: $server,
                afterState: [
                    'adoptedCount' => $adoptedCount,
                    'skippedCount' => $skippedCount,
                ],
            );
        }

        return [
            'adoptedCount' => $adoptedCount,
            'skippedCount' => $skippedCount,
        ];
    }

    private function adoptSiteIfCertificateExists(Site $site, SSHConnectionInterface $connection): bool
    {
        if ($site->enable_ssl && $site->ssl_status === SslStatus::ACTIVE) {
            return false;
        }

        $expiresAt = $this->certificateInspector->findCertificateExpiry($site->domain, $connection);

        if (! $expiresAt instanceof Carbon) {
            return false;
        }

        $beforeState = [
            'enableSsl' => $site->enable_ssl,
            'sslStatus' => $site->ssl_status instanceof SslStatus
                ? $site->ssl_status->value
                : $site->ssl_status,
        ];

        $site->forceFill([
            'enable_ssl' => true,
            'ssl_status' => SslStatus::ACTIVE->value,
            'ssl_provider' => SslProvider::LETSENCRYPT->value,
            'ssl_error' => null,
            'ssl_challenge' => $this->resolveChallenge($site),
            'ssl_expires_at' => $expiresAt,
            'ssl_checked_at' => now(),
        ])->save();

        AuditLog::record(
            operation: 'site.ssl_certificate.adopted',
            resource: $site,
            beforeState: $beforeState,
            afterState: [
                'domain' => $site->domain,
                'sslExpiresAt' => $expiresAt->toIso8601String(),
            ],
        );

        event(new SiteDnsSslStatusChanged($site->refresh()));

        return true;
    }

    private function resolveChallenge(Site $site): string
    {
        $stored = $site->ssl_challenge;

        if ($stored instanceof SslChallenge) {
            return $stored->value;
        }

        if (is_string($stored) && $stored !== '') {
            $challenge = SslChallenge::tryFrom($stored);

            if ($challenge !== null) {
                return $challenge->value;
            }
        }

        return SslChallenge::HTTP_01->value;
    }
}
