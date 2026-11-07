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

    /* "No, general fitness" this soon after answering replaces the answer. */
    'correction_minutes' => 10,

    /* A skipped question is not asked again for this many days. */
    'skip_days' => 7,

    /*
    | The written profile (notes about habits, strengths and gaps). After a
    | log it waits delay_seconds so a burst of messages makes one update, then
    | rewrites the current notes from the last recent_logs logs and the last
    | recent_turns coach questions. Once a week a full rebuild starts fresh
    | from full_logs logs to reset any drift. An AI outage is retried after
    | retry_seconds, up to the job's tries.
    */
    'rolling' => [
        'enabled' => (bool) env('PROFILE_ROLLING_ENABLED', true),
        'delay_seconds' => 120,
        'recent_logs' => 10,
        'full_logs' => 30,
        'recent_turns' => 5,
        'max_notes' => 10,
        'retry_seconds' => 300,
    ],

];
