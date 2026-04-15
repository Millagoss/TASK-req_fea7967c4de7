<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::command('profiles:recompute')->hourly();

Schedule::command('records:expire')->daily();

Schedule::command('results:recompute-stats')->hourly();

Schedule::command('backup:run')->dailyAt('02:00');
