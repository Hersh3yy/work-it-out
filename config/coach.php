<?php

declare(strict_types=1);

return [

    /*
    | What the coach sees (App\Coach\CoachContext). Kept small on purpose: the
    | whole context goes into every coach call, so it has to stay cheap and
    | the same size however long someone has been logging.
    */
    'context' => [
        'recent_logs' => (int) env('COACH_RECENT_LOGS', 12),
        'message_chars' => 300,
        'limitation_updates' => 5,
        'turns' => 4,
        'turn_hours' => 12,
        'turn_chars' => 600,
    ],

    // Output budget per answer depth (App\Coach\AnswerDepth).
    'max_tokens' => [
        'quick' => (int) env('COACH_TOKENS_QUICK', 600),
        'plan' => (int) env('COACH_TOKENS_PLAN', 1000),
        'explain' => (int) env('COACH_TOKENS_EXPLAIN', 2000),
    ],

    // After a coach answer, a message the interpreter cannot place counts as a
    // follow-up for this long ("you got that wrong", "and on the bike?").
    'follow_up_minutes' => (int) env('COACH_FOLLOW_UP_MINUTES', 30),

    // Coach questions per person per day. Logging is never capped.
    'daily_limit' => (int) env('COACH_DAILY_LIMIT', 30),

];
