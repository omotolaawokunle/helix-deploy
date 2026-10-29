<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Sites\Jobs\FetchSiteNginxConfigJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteNginxProvisioner;
use Illuminate\Support\Facades\Cache;

class UpdateNginxConfigAction
{
    public function __construct(
        private readonly SiteNginxProvisioner $siteNginxProvisioner,
    ) {}

    public function execute(Site $site, User $actor, string $config): Site
    {
        $server = $site->server;
        abort_if($server === null, 404);

        $beforeState = [
            'domain' => $site->domain,
            'runtime' => $site->runtime->value,
        ];

        $this->siteNginxProvisioner->apply($server, $site, $config);
        Cache::forget(FetchSiteNginxConfigJob::cacheKey((string) $site->getKey()));

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
