<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Services;

use App\Models\User;
use App\Modules\CronJobs\Actions\CreateCronJobAction;
use App\Modules\CronJobs\Contracts\CronJobCreatorInterface;
use App\Modules\CronJobs\Models\CronJob;
use App\Modules\Servers\Models\Server;

final class CronJobCreator implements CronJobCreatorInterface
{
    public function __construct(
        private readonly CreateCronJobAction $createCronJobAction,
    ) {
    }

    public function create(
        Server $server,
        User $actor,
        string $expression,
        string $command,
        string $user = 'www-data',
        bool $active = true,
    ): CronJob {
        return $this->createCronJobAction->execute(
            server: $server,
            actor: $actor,
            expression: $expression,
            command: $command,
            user: $user,
            active: $active,
        );
    }

    public function existsByCommand(string $serverId, string $organizationId, string $command): bool
    {
        return CronJob::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', $serverId)
            ->where('organization_id', $organizationId)
            ->where('command', $command)
            ->exists();
    }
}
