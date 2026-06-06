<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Integrations\Contracts\SiteDnsProvisionerInterface;
use App\Modules\Sites\Contracts\SiteSslProvisionerInterface;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteNginxProvisioner;

class DeleteSiteAction
{
    public function __construct(
        private readonly SiteNginxProvisioner $siteNginxProvisioner,
        private readonly SiteDnsProvisionerInterface $siteDnsProvisioner,
        private readonly SiteSslProvisionerInterface $siteSslProvisioner,
    ) {
    }

    public function execute(Site $site, User $actor): void
    {
        $server = $site->server;
        abort_if($server === null, 404);

        $beforeState = [
            'domain' => $site->domain,
            'runtime' => $site->runtime->value,
            'serverId' => $site->server_id,
            'dnsRecordIds' => $site->dns_record_ids ?? [],
            'sslStatus' => $site->ssl_status instanceof \App\Modules\Sites\Enums\SslStatus
                ? $site->ssl_status->value
                : $site->ssl_status,
        ];

        $this->siteDnsProvisioner->deleteRecords($site);
        $this->siteSslProvisioner->revoke($site);
        $this->siteNginxProvisioner->remove($server, $site);
        $this->siteNginxProvisioner->removeWebroot($server, $site->domain);

        $siteId = (string) $site->getKey();
        $site->delete();

        AuditLog::record(
            operation: 'site.deleted',
            resource: null,
            beforeState: $beforeState,
            afterState: ['id' => $siteId],
        );
    }
}
