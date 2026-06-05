<?php

declare(strict_types=1);

use App\Modules\BuildRunners\Jobs\PingBuildRunnersJob;
use App\Modules\BuildRunners\Jobs\StuckBuildWatchdogJob;
use App\Modules\Deployments\Jobs\StuckDeploymentWatchdogJob;
use App\Modules\Monitoring\Jobs\CollectServerMetricsJob;
use App\Modules\Servers\Jobs\PingServersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new PingServersJob())
    ->everyFiveMinutes()
    ->onOneServer();

Schedule::job(new PingBuildRunnersJob())
    ->everyFiveMinutes()
    ->onOneServer();

Schedule::job(new CollectServerMetricsJob())
    ->everyFiveMinutes()
    ->onOneServer();

Schedule::job(new StuckDeploymentWatchdogJob())
    ->everyTenMinutes()
    ->onOneServer();

Schedule::job(new StuckBuildWatchdogJob())
    ->everyTenMinutes()
    ->onOneServer();

