<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\CronJobs\Contracts\DiscoveredCronImporterInterface;
use App\Modules\CronJobs\Models\CronJob;
use App\Modules\Servers\DTOs\DiscoveredCronSnapshot;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Support\SitePathMatcher;

final class AdoptDiscoveredCronJobsAction implements DiscoveredCronImporterInterface
{
    public function __construct(
        private readonly SitePathMatcher $sitePaths,
    ) {
    }

    public function import(Server $server, array $jobs, array $sitePaths): array
    {
        $actorId = (string) $server->created_by;

        if ($actorId === '') {
            return ['created' => 0, 'updated' => 0];
        }

        $created = 0;
        $updated = 0;

        foreach ($jobs as $job) {
            $result = $this->adopt($server, $job, $sitePaths, $actorId);

            if ($result === 'created') {
                $created++;
            }

            if ($result === 'updated') {
                $updated++;
            }
        }

        if ($created > 0 || $updated > 0) {
            AuditLog::record(
                operation: 'server.cron_jobs_adopted',
                resource: $server,
                afterState: [
                    'server_id' => (string) $server->getKey(),
                    'created' => $created,
                    'updated' => $updated,
                ],
            );
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @param list<array{siteId: string, currentPath: string}> $sitePaths
     */
    private function adopt(Server $server, DiscoveredCronSnapshot $job, array $sitePaths, string $actorId): ?string
    {
        $existing = CronJob::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $server->getKey())
            ->where('organization_id', (string) $server->organization_id)
            ->where('expression', $job->expression)
            ->where('command', $job->command)
            ->where('user', $job->user)
            ->first();

        $siteId = $this->sitePaths->match('', $job->command, $sitePaths);

        if ($existing === null) {
            CronJob::query()->withoutGlobalScope('owned_by_organization')->create([
                'server_id' => (string) $server->getKey(),
                'organization_id' => (string) $server->organization_id,
                'site_id' => $siteId,
                'expression' => $job->expression,
                'command' => $job->command,
                'user' => $job->user,
                'active' => true,
                'external' => true,
                'created_by' => $actorId,
            ]);

            return 'created';
        }

        if ($siteId === null || $existing->site_id === $siteId) {
            return null;
        }

        $existing->forceFill(['site_id' => $siteId])->save();

        return 'updated';
    }
}
