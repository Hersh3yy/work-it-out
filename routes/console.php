<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Once a week the written profile starts fresh, so small errors in the
// rolling updates do not pile up.
Schedule::command('profile:rebuild --all --full')->weeklyOn(1, '04:00')->withoutOverlapping();

// Monday morning: last week's AI calls, tokens and cost per job, kept in
// storage/logs/ai-usage.log (php artisan ai:usage prints it any time).
Schedule::command('ai:usage --since=7d --by=job')->weeklyOn(1, '05:00')->appendOutputTo(storage_path('logs/ai-usage.log'));
