<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Servers\Models\ServerGroup;

class DeleteServerGroupAction
{
    public function execute(ServerGroup $group): void
    {
        $beforeState = [
            'name' => $group->name,
            'description' => $group->description,
            'serverIds' => $group->servers()->pluck('servers.id')->map(
                static fn (mixed $id): string => (string) $id,
            )->all(),
        ];

        AuditLog::record(
            operation: 'server_group.deleted',
            resource: $group,
            beforeState: $beforeState,
        );

        $group->delete();
    }
}
