<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Daemons\Contracts\DiscoveredDaemonImporterInterface;
use App\Modules\Daemons\Enums\DaemonStatus;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Servers\DTOs\DiscoveredDaemonSnapshot;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Support\SitePathMatcher;

final class AdoptDiscoveredDaemonsAction implements DiscoveredDaemonImporterInterface
{
    public function __construct(
        private readonly SitePathMatcher $sitePaths,
    ) {
    }

    public function import(Server $server, array $daemons, array $sitePaths): array
    {
        $actorId = (string) $server->created_by;

        if ($actorId === '') {
            return ['created' => 0, 'updated' => 0];
        }

        $created = 0;
        $updated = 0;

        foreach ($daemons as $daemon) {
            $result = $this->adopt($server, $daemon, $sitePaths, $actorId);

            if ($result === 'created') {
                $created++;
            }

            if ($result === 'updated') {
                $updated++;
            }
        }

        if ($created > 0 || $updated > 0) {
            AuditLog::record(
                operation: 'server.daemons_adopted',
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
    private function adopt(Server $server, DiscoveredDaemonSnapshot $daemon, array $sitePaths, string $actorId): ?string
    {
        $existing = SupervisorProcess::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $server->getKey())
            ->where('organization_id', (string) $server->organization_id)
            ->where('name', $daemon->name)
            ->first();

        $siteId = $this->sitePaths->match((string) $daemon->directory, $daemon->command, $sitePaths);
        $status = DaemonStatus::tryFrom($daemon->status) ?? DaemonStatus::STOPPED;

        if ($existing === null) {
            SupervisorProcess::query()->withoutGlobalScope('owned_by_organization')->create([
                'server_id' => (string) $server->getKey(),
                'organization_id' => (string) $server->organization_id,
                'site_id' => $siteId,
                'name' => $daemon->name,
                'command' => $daemon->command,
                'directory' => $daemon->directory,
                'user' => $daemon->user,
                'processes' => $daemon->processes,
                'status' => $status,
                'config_path' => $daemon->configPath,
                'created_by' => $actorId,
            ]);

            return 'created';
        }

        $changes = [
            'command' => $daemon->command,
            'directory' => $daemon->directory,
            'user' => $daemon->user,
            'processes' => $daemon->processes,
            'status' => $status,
            'config_path' => $daemon->configPath,
        ];

        if ($siteId !== null) {
            $changes['site_id'] = $siteId;
        }

        $dirty = false;

        foreach ($changes as $attribute => $value) {
            $current = $existing->getAttribute($attribute);

            if ($current instanceof \BackedEnum) {
                $current = $current->value;
            }

            $compare = $value instanceof \BackedEnum ? $value->value : $value;

            if ($current !== $compare) {
                $dirty = true;
                break;
            }
        }

        if (! $dirty) {
            return null;
        }

        $existing->forceFill($changes)->save();

        return 'updated';
    }
}
