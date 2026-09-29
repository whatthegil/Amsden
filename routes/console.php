<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Run by `php artisan schedule:run` every minute - on Laravel Cloud, turn on
| the scheduler for the environment; elsewhere, a cron entry. Nothing here is
| urgent: skipping a day only means a day's temporary files wait a day longer.
|
*/

// A copy of the database before the day's tidy, keeping the last two weeks.
Schedule::command('archive:backup')->dailyAt('01:30')->withoutOverlapping();

Schedule::command('archive:tidy')->dailyAt('02:00')->withoutOverlapping();

// Failed jobs are kept a month for anyone looking into them, then dropped.
Schedule::command('queue:prune-failed', ['--hours' => 720])->dailyAt('02:15');
