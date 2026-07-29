<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\CronJobs\Contracts\CronJobCreatorInterface;
use App\Modules\Daemons\Contracts\DaemonCreatorInterface;
use App\Modules\Daemons\DTOs\CreateDaemonDTO;
use App\Modules\Sites\Enums\LaravelWorkerType;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Exceptions\LaravelWorkersAlreadyConfiguredException;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\LaravelWorkerPresetBuilder;
use App\Modules\Servers\Models\Server;

final class SetupLaravelWorkersAction
{
    public function __construct(
        private readonly LaravelWorkerPresetBuilder $presetBuilder,
        private readonly DaemonCreatorInterface $daemonCreator,
        private readonly CronJobCreatorInterface $cronJobCreator,
    ) {
    }

    public function execute(Site $site, LaravelWorkerType $workerType, User $actor): void
    {
        if ($site->runtime !== Runtime::PHP) {
            throw new \InvalidArgumentException('Laravel workers can only be configured for PHP sites.');
        }

        $server = $site->server;

        if (! $server instanceof Server) {
            throw new \RuntimeException('Site server could not be resolved.');
        }

        $preset = $this->presetBuilder->build($site, $workerType);

        if ($this->daemonCreator->existsByName(
            (string) $server->getKey(),
            (string) $server->organization_id,
            $preset->daemonName,
        )) {
            throw new LaravelWorkersAlreadyConfiguredException(
                siteId: (string) $site->getKey(),
                reason: sprintf('A daemon named [%s] already exists on this server.', $preset->daemonName),
            );
        }

        if ($this->cronJobCreator->existsByCommand(
            (string) $server->getKey(),
            (string) $server->organization_id,
            $preset->cronCommand,
        )) {
            throw new LaravelWorkersAlreadyConfiguredException(
                siteId: (string) $site->getKey(),
                reason: 'A scheduler cron job for this site path already exists on this server.',
            );
        }

        $this->daemonCreator->queueCreate(
            serverId: (string) $server->getKey(),
            actorId: (string) $actor->getKey(),
            dto: new CreateDaemonDTO(
                name: $preset->daemonName,
                command: $preset->daemonCommand,
                directory: $preset->daemonDirectory,
                user: $preset->daemonUser,
                processes: $preset->daemonProcesses,
            ),
        );

        $this->cronJobCreator->create(
            server: $server,
            actor: $actor,
            expression: $preset->cronExpression,
            command: $preset->cronCommand,
            user: $preset->cronUser,
            active: true,
        );

        AuditLog::record(
            operation: 'site.laravel_workers_setup',
            resource: $site,
            afterState: [
                'site_id' => (string) $site->getKey(),
                'server_id' => (string) $server->getKey(),
                'worker_type' => $workerType->value,
                'daemon_name' => $preset->daemonName,
                'cron_command' => $preset->cronCommand,
            ],
        );
    }
}
