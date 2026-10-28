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
