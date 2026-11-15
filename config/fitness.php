<?php

declare(strict_types=1);

/*
| Every number the game's foundation computes with. Each line names its
| source; the same sources are listed under "Numbers in code" in
| docs/method-cards-2026-11-14.md. Values marked "convention" are common
| coaching practice, not a study, and are ours to tune.
*/
return [

    // How far back the history for power and readiness reaches: the longest window below.
    'history_days' => 56,

    'power' => [
        // Method card 3, measure: the e1RM trend over four to eight weeks.
        'strength_window_days' => 56,

        // Method card 3 with Hiren's pick of 15 Nov: Epley, weight x (1 + reps / 30), from sets of 1 to 10 reps.
        'epley_max_reps' => 10,

        // Method card 3, strength standards: e1RM per kilo of body weight for an intermediate lifter. From memory, to verify.
        'strength_standards' => [
            'Squat' => 1.25,
            'Deadlift' => 1.5,
            'Bench Press' => 1.0,
            'Overhead Press' => 0.65,
        ],

        // Method card 2, measure two: WHO 2020, 150 minutes of moderate activity a week.
        'cardio_window_days' => 7,
        'cardio_minutes_guideline' => 150,

        // Method card 1, measure: the longest run of the last 30 days.
        'longest_run_window_days' => 30,

        // Method card 4, tests for brace and carry: convention, a two-minute plank and a farmer's carry at half body weight per hand.
        'core_window_days' => 56,
        'core_hold_standard_seconds' => 120,
        'carry_per_hand_standard' => 0.5,

        // Convention: two rest days in a week. A limitation takes this share off recovery, by severity (unknown counts as moderate).
        'rest_window_days' => 7,
        'rest_days_standard' => 2,
        'limitation_penalty' => [
            'mild' => 0.1,
            'moderate' => 0.25,
            'severe' => 0.5,
        ],
    ],

    'readiness' => [
        // Convention, about 48 to 72 hours between hard sessions for the same quality (ACSM guidance, from memory, to verify): readiness on the day it worked and the days after; full from then on.
        'full' => 100,
        'after_training' => [0 => 40, 1 => 40, 2 => 70],

        // Convention (Feetness, 15 November): an active limitation caps the qualities that load its area, by severity (unknown counts as moderate); an illness caps every quality.
        'limitation_cap' => [
            'mild' => 80,
            'moderate' => 60,
            'severe' => 30,
        ],
        'illness_cap' => 50,
    ],

    'level' => [
        // Convention (Feetness, 15 November): every session that trains a quality earns it this many points; level is the sum, so it only grows.
        'points_per_session' => 1,
    ],

];
