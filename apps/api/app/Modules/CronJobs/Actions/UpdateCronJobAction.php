<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\CronJobs\Jobs\SyncCronJobsJob;
use App\Modules\CronJobs\Models\CronJob;
use App\Modules\CronJobs\Services\CronService;

class UpdateCronJobAction
{
    public function __construct(
        private readonly CronService $cronService,
    ) {
    }

    /**
     * @param array{expression?: string, command?: string, user?: string, active?: bool} $attributes
     */
    public function execute(CronJob $cronJob, array $attributes): CronJob
    {
        $beforeState = [
            'expression' => $cronJob->expression,
            'command' => $cronJob->command,
            'user' => $cronJob->user,
            'active' => $cronJob->active,
        ];

        if (isset($attributes['expression'])) {
            $this->cronService->validate($attributes['expression']);
        }

        $cronJob->fill($attributes);
        $cronJob->save();

        AuditLog::record(
            operation: 'cron_job.updated',
            resource: $cronJob,
            beforeState: $beforeState,
            afterState: [
                'expression' => $cronJob->expression,
                'command' => $cronJob->command,
                'user' => $cronJob->user,
                'active' => $cronJob->active,
            ],
        );

        SyncCronJobsJob::dispatch((string) $cronJob->server_id);

        return $cronJob->refresh();
    }
}
