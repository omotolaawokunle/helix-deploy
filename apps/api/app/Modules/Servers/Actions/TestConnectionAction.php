<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Servers\Jobs\VerifyServerConnectionJob;
use App\Modules\Servers\Models\Server;

class TestConnectionAction
{
    public function execute(Server $server, User $actor): void
    {
        $beforeState = [
            'status' => $server->status?->value,
        ];

        $server->forceFill([
            'status' => ServerStatus::CONNECTING->value,
        ])->save();

        AuditLog::record(
            operation: 'server.connection_test_requested',
            resource: $server,
            beforeState: $beforeState,
            afterState: [
                'status' => ServerStatus::CONNECTING->value,
                'requestedBy' => (string) $actor->getKey(),
            ],
        );

        VerifyServerConnectionJob::dispatch((string) $server->getKey());
    }
}
