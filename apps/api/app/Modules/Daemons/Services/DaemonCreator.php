<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Services;

use App\Modules\Daemons\Contracts\DaemonCreatorInterface;
use App\Modules\Daemons\DTOs\CreateDaemonDTO;
use App\Modules\Daemons\Jobs\RunDaemonOperationJob;
use App\Modules\Daemons\Models\SupervisorProcess;

final class DaemonCreator implements DaemonCreatorInterface
{
    public function queueCreate(string $serverId, string $actorId, CreateDaemonDTO $dto): void
    {
        RunDaemonOperationJob::dispatch(
            operation: 'create',
            serverId: $serverId,
            actorId: $actorId,
            dto: $dto,
        );
    }

    public function existsByName(string $serverId, string $organizationId, string $name): bool
    {
        return SupervisorProcess::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', $serverId)
            ->where('organization_id', $organizationId)
            ->where('name', $name)
            ->exists();
    }
}
