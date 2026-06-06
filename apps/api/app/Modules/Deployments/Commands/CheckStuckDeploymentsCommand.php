<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Commands;

use App\Modules\Deployments\Jobs\StuckDeploymentWatchdogJob;
use Illuminate\Console\Command;

final class CheckStuckDeploymentsCommand extends Command
{
    protected $signature = 'deploy:check-stuck';

    protected $description = 'Run the stuck deployment watchdog manually.';

    public function handle(): int
    {
        (new StuckDeploymentWatchdogJob())->handle();

        $this->info('Stuck deployment watchdog completed.');

        return self::SUCCESS;
    }
}
