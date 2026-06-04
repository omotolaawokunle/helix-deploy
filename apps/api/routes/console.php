<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Modules\Deployments\Jobs\StuckDeploymentWatchdogJob;
use App\Modules\Servers\Jobs\PingServersJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new PingServersJob(), 'monitoring')
    ->everyFiveMinutes();

Schedule::job(new StuckDeploymentWatchdogJob(), 'deployments')
    ->everyTenMinutes();
