<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the user means by a message, the first thing decided about it.
 * Lab only for now (2026-10-07): tried in isolation before it replaces
 * MessageKind in the real chat path. English only.
 */
enum Intent: string
{
    case WorkoutLog = 'workout_log';
    case ProfileUpdate = 'profile_update';
    case Feeling = 'feeling';
    case Question = 'question';
    case Nonsense = 'nonsense';

    /**
     * The options a classifier model chooses from, each with what it means.
     *
     * @return array<string, string>
     */
    public static function criteria(): array
    {
        return [
            self::WorkoutLog->value => 'reports training that was done: exercises, sets, reps, weights, runs, rides or sports',
            self::ProfileUpdate->value => 'a fact about the user to remember: body weight, height, age, a goal or target, how many days a week they train',
            self::Feeling->value => 'how the user feels: energy, mood, sleep, soreness, pain or an injury',
            self::Question->value => 'asks the coach for advice, a plan or feedback',
            self::Nonsense->value => 'unrelated to fitness, a joke, or meaningless',
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
