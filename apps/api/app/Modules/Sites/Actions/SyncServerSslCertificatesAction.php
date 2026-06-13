<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Contracts\SiteSslCertificateInspectorInterface;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Models\Site;
use App\Packages\SSH\SSHManager;

final class SyncServerSslCertificatesAction
{
    public function __construct(
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
        private readonly SiteSslCertificateInspectorInterface $certificateInspector,
    ) {
    }

    /**
     * @return array{syncedCount: int, failedCount: int}
     */
    public function execute(Server $server): array
    {
        $sites = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $server->getKey())
            ->where('enable_ssl', true)
            ->where('ssl_status', SslStatus::ACTIVE->value)
            ->get();

        if ($sites->isEmpty()) {
            return ['syncedCount' => 0, 'failedCount' => 0];
        }

        $connection = $this->sshManager->connect($server, $this->credentialVault)->connect();
        $syncedCount = 0;
        $failedCount = 0;

        try {
            foreach ($sites as $site) {
                if (! $site instanceof Site) {
                    continue;
                }

                try {
                    $expiresAt = $this->certificateInspector->inspect($site, $connection);

                    $site->forceFill([
                        'ssl_expires_at' => $expiresAt,
                        'ssl_checked_at' => now(),
                    ])->save();

                    $syncedCount++;
                } catch (\Throwable) {
                    $failedCount++;
                }
            }
        } finally {
            $connection->disconnect();
        }

        AuditLog::record(
            operation: 'server.ssl_certificates.synced',
            resource: $server,
            afterState: [
                'syncedCount' => $syncedCount,
                'failedCount' => $failedCount,
            ],
        );

        return [
            'syncedCount' => $syncedCount,
            'failedCount' => $failedCount,
        ];
    }
}
