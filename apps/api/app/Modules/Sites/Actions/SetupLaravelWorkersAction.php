<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\CronJobs\Contracts\CronJobCreatorInterface;
use App\Modules\CronJobs\Jobs\SyncCronJobsJob;
use App\Modules\Daemons\Contracts\DaemonCreatorInterface;
use App\Modules\Daemons\DTOs\CreateDaemonDTO;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\LaravelWorkerType;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Exceptions\LaravelWorkersAlreadyConfiguredException;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\LaravelWorkerPresetBuilder;

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
        $serverId = (string) $server->getKey();
        $organizationId = (string) $server->organization_id;

        $daemonExists = $this->daemonCreator->existsByName(
            $serverId,
            $organizationId,
            $preset->daemonName,
        );

        $cronExists = $this->cronJobCreator->existsByCommand(
            $serverId,
            $organizationId,
            $preset->cronCommand,
        );

        if ($daemonExists && $cronExists) {
            // Heal cases where the DB row exists but the on-server crontab sync failed.
            SyncCronJobsJob::dispatch($serverId);

            throw new LaravelWorkersAlreadyConfiguredException(
                siteId: (string) $site->getKey(),
                reason: sprintf(
                    'Laravel workers are already configured (daemon [%s] and scheduler cron). Check the server Daemons and Cron tabs.',
                    $preset->daemonName,
                ),
            );
        }

        if (! $daemonExists) {
            $this->daemonCreator->queueCreate(
                serverId: $serverId,
                actorId: (string) $actor->getKey(),
                dto: new CreateDaemonDTO(
                    name: $preset->daemonName,
                    command: $preset->daemonCommand,
                    directory: $preset->daemonDirectory,
                    user: $preset->daemonUser,
                    processes: $preset->daemonProcesses,
                ),
            );
        }

        if (! $cronExists) {
            $this->cronJobCreator->create(
                server: $server,
                actor: $actor,
                expression: $preset->cronExpression,
                command: $preset->cronCommand,
                user: $preset->cronUser,
                active: true,
            );
        } else {
            SyncCronJobsJob::dispatch($serverId);
        }

        AuditLog::record(
            operation: 'site.laravel_workers_setup',
            resource: $site,
            afterState: [
                'site_id' => (string) $site->getKey(),
                'server_id' => $serverId,
                'worker_type' => $workerType->value,
                'daemon_name' => $preset->daemonName,
                'cron_command' => $preset->cronCommand,
                'daemon_created' => ! $daemonExists,
                'cron_created' => ! $cronExists,
            ],
        );
    }
}
