<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Servers\DTOs\DiscoveredSiteSnapshot;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Contracts\DiscoveredSiteImporterInterface;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\EnvVarPullStrategy;
use App\Modules\Sites\Jobs\ApplyEnvVarsPullJob;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Str;

class ImportDiscoveredSitesAction implements DiscoveredSiteImporterInterface
{
    /**
     * @param list<DiscoveredSiteSnapshot> $sites
     * @return array{created: int, updated: int}
     */
    public function import(Server $server, array $sites): array
    {
        $created = 0;
        $updated = 0;

        foreach ($sites as $discoveredSite) {
            $result = $this->importSingleSite($server, $discoveredSite);

            if ($result === 'created') {
                $created++;
                continue;
            }

            if ($result === 'updated') {
                $updated++;
            }
        }

        if ($created > 0 || $updated > 0) {
            AuditLog::record(
                operation: 'server.sites_discovered',
                resource: $server,
                beforeState: null,
                afterState: [
                    'created' => $created,
                    'updated' => $updated,
                ],
            );
        }

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    private function importSingleSite(Server $server, DiscoveredSiteSnapshot $discoveredSite): ?string
    {
        $existing = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $server->getKey())
            ->where('domain', $discoveredSite->domain)
            ->first();

        if ($existing === null) {
            $site = Site::query()->create([
                'id' => (string) Str::uuid(),
                'server_id' => (string) $server->getKey(),
                'organization_id' => (string) $server->organization_id,
                'project_id' => $server->project_id,
                'environment_id' => $server->environment_id,
                'domain' => $discoveredSite->domain,
                'aliases' => [],
                'webroot' => $discoveredSite->webroot,
                'runtime' => $this->resolveRuntime($discoveredSite->runtime)->value,
                'deploy_mode' => DeployMode::GIT->value,
                'deploy_branch' => 'main',
                'php_version' => $this->resolvePhpVersion($server, $discoveredSite->runtime),
                'status' => SiteStatus::DISCOVERED->value,
            ]);

            if ($server->isManaged()) {
                ApplyEnvVarsPullJob::dispatch(
                    siteId: (string) $site->getKey(),
                    strategy: EnvVarPullStrategy::ADD_NEW,
                );
            }

            return 'created';
        }

        if ($existing->status !== SiteStatus::DISCOVERED) {
            return null;
        }

        $existing->forceFill([
            'webroot' => $discoveredSite->webroot,
            'runtime' => $this->resolveRuntime($discoveredSite->runtime)->value,
            'php_version' => $this->resolvePhpVersion($server, $discoveredSite->runtime),
        ])->save();

        return 'updated';
    }

    private function resolveRuntime(string $runtime): Runtime
    {
        return Runtime::tryFrom($runtime) ?? Runtime::STATIC;
    }

    private function resolvePhpVersion(Server $server, string $runtime): ?string
    {
        if ($runtime !== 'php') {
            return null;
        }

        $serverPhpVersion = $server->php_version;

        if (! is_string($serverPhpVersion) || $serverPhpVersion === '') {
            return '8.3';
        }

        if (preg_match('/PHP\s(\d+\.\d+)/', $serverPhpVersion, $matches) === 1) {
            return $matches[1];
        }

        return '8.3';
    }
}
