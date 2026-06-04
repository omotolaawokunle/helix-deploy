<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\CronJobs\Jobs\SyncCronJobsJob;
use App\Modules\CronJobs\Models\CronJob;
use App\Modules\CronJobs\Services\CronService;
use App\Modules\Servers\Models\Server;

class CreateCronJobAction
{
    public function __construct(
        private readonly CronService $cronService,
    ) {
    }

    public function execute(
        Server $server,
        User $actor,
        string $expression,
        string $command,
        string $user = 'www-data',
        bool $active = true,
    ): CronJob {
        $this->cronService->validate($expression);

        $cronJob = CronJob::query()->create([
            'server_id' => (string) $server->getKey(),
            'organization_id' => (string) $server->organization_id,
            'expression' => $expression,
            'command' => $command,
            'user' => $user,
            'active' => $active,
            'created_by' => (string) $actor->getKey(),
        ]);

        AuditLog::record(
            operation: 'cron_job.created',
            resource: $cronJob,
            afterState: [
                'server_id' => (string) $server->getKey(),
                'expression' => $expression,
                'active' => $active,
            ],
        );

        SyncCronJobsJob::dispatch((string) $server->getKey());

        return $cronJob;
    }
}
