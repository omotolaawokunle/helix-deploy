<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\CronJobs\Jobs\SyncCronJobsJob;
use App\Modules\CronJobs\Models\CronJob;

class ToggleCronJobAction
{
    public function execute(CronJob $cronJob): CronJob
    {
        $beforeActive = $cronJob->active;

        $cronJob->forceFill(['active' => ! $cronJob->active])->save();

        AuditLog::record(
            operation: 'cron_job.toggled',
            resource: $cronJob,
            beforeState: ['active' => $beforeActive],
            afterState: ['active' => $cronJob->active],
        );

        SyncCronJobsJob::dispatch((string) $cronJob->server_id);

        return $cronJob->refresh();
    }
}
