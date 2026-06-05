<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Models\ServerGroup;
use Illuminate\Validation\ValidationException;

class SyncServerGroupServersAction
{
    /**
     * @param list<string> $serverIds
     */
    public function execute(ServerGroup $group, array $serverIds): ServerGroup
    {
        $serverIds = array_values(array_unique($serverIds));

        if ($serverIds !== []) {
            $validCount = Server::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $group->organization_id)
                ->whereIn('id', $serverIds)
                ->count();

            if ($validCount !== count($serverIds)) {
                throw ValidationException::withMessages([
                    'serverIds' => ['One or more servers do not belong to this organization.'],
                ]);
            }
        }

        $beforeState = [
            'serverIds' => $group->servers()->pluck('servers.id')->map(
                static fn (mixed $id): string => (string) $id,
            )->all(),
        ];

        $group->servers()->sync($serverIds);

        AuditLog::record(
            operation: 'server_group.servers_synced',
            resource: $group,
            beforeState: $beforeState,
            afterState: [
                'serverIds' => $serverIds,
            ],
        );

        return $group->refresh()->load('servers');
    }
}
