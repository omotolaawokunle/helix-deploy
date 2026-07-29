<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Contracts;

use App\Modules\Daemons\DTOs\CreateDaemonDTO;

interface DaemonCreatorInterface
{
    public function queueCreate(string $serverId, string $actorId, CreateDaemonDTO $dto): void;

    public function existsByName(string $serverId, string $organizationId, string $name): bool;
}
