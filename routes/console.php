<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

$interval = (int) env('THREAD_SYNC_INTERVAL_MINUTES', 60);

if ($interval < 60) {
    $cron = "*/{$interval} * * * *";
} elseif ($interval % 60 === 0) {
    $hours = $interval / 60;
    $cron = "0 */{$hours} * * *";
} else {
    $minutes = $interval % 60;
    $hours = floor($interval / 60);
    $cron = "*/{$minutes} */{$hours} * * *";
}

Schedule::command('threads:sync-views')
    ->cron($cron)
    ->withoutOverlapping();

Schedule::command('threads:sync-likes')
    ->cron($cron)
    ->withoutOverlapping();

$time = env('WALLET_SYNC_TIME', '00:00:00');
[$hour, $minute, $second] = explode(':', $time);

Schedule::command('wallets:sync-views')
    ->dailyAt(sprintf('%02d:%02d', $hour, $minute))
    ->withoutOverlapping()
    ->runInBackground();


