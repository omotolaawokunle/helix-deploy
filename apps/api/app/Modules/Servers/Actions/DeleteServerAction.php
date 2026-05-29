<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Servers\Jobs\DeleteServerJob;
use App\Modules\Servers\Models\Server;

class DeleteServerAction
{
    public function execute(Server $server, User $actor): void
    {
        AuditLog::record(
            operation: 'server.delete_requested',
            resource: $server,
            beforeState: [
                'hostname' => $server->hostname,
                'status' => $server->status?->value,
            ],
            afterState: [
                'requestedBy' => (string) $actor->getKey(),
                'deleteAt' => now()->addSeconds(30)->toIso8601String(),
            ],
        );

        DeleteServerJob::dispatch(
            serverId: (string) $server->getKey(),
            actorId: (string) $actor->getKey(),
        )->delay(now()->addSeconds(30));
    }
}
