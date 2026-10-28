<?php

declare(strict_types=1);

use App\Profile\Questions\MainGoal;
use App\Profile\Questions\TrainingDaysPerWeek;

return [

    /*
    | The facts the profile asks for, in order: the first one not yet known
    | (and not skipped recently) is asked next. Add, remove or reorder here.
    */
    'questions' => [
        TrainingDaysPerWeek::class,
        MainGoal::class,
    ],

    /* At most one question per this many hours. */
    'hours_between_questions' => 20,

    /* An asked question can be answered for this long. */
    'answer_window_hours' => 24,

    /* A skipped question is not asked again for this many days. */
    'skip_days' => 7,

    /*
    | The written profile (notes about habits, strengths and gaps). After a
    | log it waits delay_seconds so a burst of messages makes one update, then
    | rewrites the current notes from the last recent_logs logs. Once a week a
    | full rebuild starts fresh from full_logs logs to reset any drift.
    */
    'rolling' => [
        'enabled' => (bool) env('PROFILE_ROLLING_ENABLED', true),
        'delay_seconds' => 120,
        'recent_logs' => 10,
        'full_logs' => 30,
        'max_notes' => 8,
    ],

];
