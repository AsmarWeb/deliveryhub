<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('deliveries:run-recurring')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('deliveries:auto-dispatch')->everyMinute()->withoutOverlapping(5);

Schedule::command('notifications:send-push')->everyMinute()->withoutOverlapping(5);
