<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\CronJobs\Jobs\SyncCronJobsJob;
use App\Modules\CronJobs\Models\CronJob;

class DeleteCronJobAction
{
    public function execute(CronJob $cronJob): void
    {
        $server = $cronJob->server;
        $serverId = (string) $cronJob->server_id;

        $beforeState = [
            'expression' => $cronJob->expression,
            'command' => $cronJob->command,
            'active' => $cronJob->active,
        ];

        $cronJob->delete();

        AuditLog::record(
            operation: 'cron_job.deleted',
            resource: $server,
            beforeState: $beforeState,
        );

        SyncCronJobsJob::dispatch($serverId);
    }
}
