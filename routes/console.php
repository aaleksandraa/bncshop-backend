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
    Schedule::command('bnc:sync-eline-scheduled')->dailyAt($time)->withoutOverlapping(55);
}

Schedule::command('bnc:sync-eline-content-scheduled')
    ->dailyAt((string) config('bnc.eline_content_sync_time', '03:00'))
    ->withoutOverlapping(55);

foreach (config('bnc.olx_sync_times', ['06:00', '18:00']) as $time) {
    Schedule::command('bnc:sync-olx-scheduled')->dailyAt($time);
}

Schedule::command('bnc:sync-olx-stock')->hourly()->withoutOverlapping(50);

Schedule::command('analytics:aggregate-daily')->dailyAt('00:05');
Schedule::command('bnc:loyalty-expire-points')->dailyAt('01:00');
Schedule::command('sitemap:generate')->dailyAt('02:00');

Schedule::command('bnc:ananas-daily catalog')->dailyAt('00:10')->withoutOverlapping(90);
Schedule::command('bnc:ananas-daily price_stock')->dailyAt('00:25')->withoutOverlapping(90);
Schedule::command('bnc:ananas-daily discounts')->dailyAt('02:30')->withoutOverlapping(90);
Schedule::command('bnc:ananas-daily import')->dailyAt('08:00')->withoutOverlapping(90);
Schedule::command('bnc:ananas-daily intraday_stock')->dailyAt('10:00')->withoutOverlapping(45);

Schedule::command('bnc:ananas-pull-orders')->everyTenMinutes()->withoutOverlapping(8);
Schedule::command('bnc:ananas-pull-orders')->dailyAt('00:20')->withoutOverlapping(8);
Schedule::command('bnc:ananas-pull-orders')->dailyAt('09:55')->withoutOverlapping(8);
