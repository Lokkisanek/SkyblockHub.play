<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
*/
Schedule::command('bazaar:fetch')->everyMinute()->withoutOverlapping();
Schedule::command('bin:fetch')->everyFiveMinutes()->withoutOverlapping();
