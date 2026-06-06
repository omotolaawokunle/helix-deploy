<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Commands;

use App\Modules\CronJobs\Jobs\SyncCronJobsJob;
use App\Modules\Servers\Models\Server;
use Illuminate\Console\Command;

final class SyncServerCronsCommand extends Command
{
    protected $signature = 'server:sync-crons {server_id : Server UUID}';

    protected $description = 'Re-sync the crontab for all jobs on a server.';

    public function handle(): int
    {
        $serverId = (string) $this->argument('server_id');

        $exists = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($serverId)
            ->exists();

        if (! $exists) {
            $this->error('Server not found.');

            return self::FAILURE;
        }

        SyncCronJobsJob::dispatch($serverId);

        $this->info(sprintf('Cron sync queued for server %s (commands queue).', $serverId));

        return self::SUCCESS;
    }
}
