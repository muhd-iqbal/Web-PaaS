<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('databases:refresh-usage')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('subscriptions:expire-trials')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('billing:reconcile-toyyibpay')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('usage:import-traefik')->everyMinute()->withoutOverlapping();
Schedule::command('monitoring:collect')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('monitoring:prune')->dailyAt('02:30')->withoutOverlapping();
