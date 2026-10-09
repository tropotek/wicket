<?php

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
