<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule tasks
Schedule::command('articles:fetch')->everyThirtyMinutes();
Schedule::command('articles:generate-summaries')->daily();
Schedule::command('articles:cleanup')->hourly();
Schedule::command('classifier:update')->weekly();
