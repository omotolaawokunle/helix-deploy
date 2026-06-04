<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Sites\Contracts\NginxConfigGeneratorInterface;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteNginxProvisioner;
class UpdateNginxConfigAction
{
    public function __construct(
        private readonly NginxConfigGeneratorInterface $nginxConfigGenerator,
        private readonly SiteNginxProvisioner $siteNginxProvisioner,
    ) {
    }

    public function execute(Site $site, User $actor, string $config): Site
    {
        $server = $site->server;
        abort_if($server === null, 404);

        $beforeState = [
            'domain' => $site->domain,
            'runtime' => $site->runtime->value,
        ];

        $this->siteNginxProvisioner->apply($server, $site, $config);

        AuditLog::record(
            operation: 'site.nginx_config_updated',
            resource: $site,
            beforeState: $beforeState,
            afterState: [
                'domain' => $site->domain,
                'runtime' => $site->runtime->value,
            ],
        );

        return $site->refresh();
    }
}
