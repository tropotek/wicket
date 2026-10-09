<?php

/*
| Scheduled jobs. Something must run the scheduler:
|   - long-running:  php artisan schedule:work            (the shipped image's entry script does this)
|   - or crontab:    * * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1
| /up reports `scheduler` (last heartbeat) and `cron` (last housekeeping run).
*/

use App\Support\HealthStatus;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => Cache::forever(HealthStatus::HEARTBEAT_KEY, time()))
    ->name('scheduler-heartbeat')
    ->everyMinute();

Schedule::command('wicket:cron')->hourly()->withoutOverlapping();

// Links and search are kept current on every write; a nightly rebuild heals anything missed.
Schedule::command('wicket:reindex')->dailyAt('03:30')->withoutOverlapping();
