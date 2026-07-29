<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Contracts;

use App\Models\User;
use App\Modules\CronJobs\Models\CronJob;
use App\Modules\Servers\Models\Server;

interface CronJobCreatorInterface
{
    public function create(
        Server $server,
        User $actor,
        string $expression,
        string $command,
        string $user = 'www-data',
        bool $active = true,
    ): CronJob;

    public function existsByCommand(string $serverId, string $organizationId, string $command): bool;
}
