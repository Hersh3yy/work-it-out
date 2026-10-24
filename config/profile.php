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

];
