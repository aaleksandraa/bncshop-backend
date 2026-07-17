<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('bnc:sync-scheduled')->everyFiveMinutes();

foreach (config('bnc.eline_sync_times', ['06:00', '18:00']) as $time) {
    Schedule::command('bnc:sync-eline-scheduled')->dailyAt($time);
}

foreach (config('bnc.olx_sync_times', ['06:00', '18:00']) as $time) {
    Schedule::command('bnc:sync-olx-scheduled')->dailyAt($time);
}

Schedule::command('analytics:aggregate-daily')->dailyAt('00:05');
Schedule::command('bnc:loyalty-expire-points')->dailyAt('01:00');
Schedule::command('sitemap:generate')->dailyAt('02:00');
