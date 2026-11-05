<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

use App\Enums\LogType;

/**
 * A workout with no exercise and no duration is nothing to save: the model
 * read the names into the summary but returned no rows (seen on a four-line
 * message, 2026-11-05). Ask for it again instead of saving a hollow session
 * the coach would then plan around.
 */
final class WorkoutNeedsAnExercise implements InterpretationRule
{
    public const string REPLY = "I read that as a workout but couldn't make out the exercises, so nothing was saved. One per line works best, like:\n"
        ."chest supported row 41kg 5x10\n"
        ."leg curl 50kg 3x10\n"
        .'treadmill 1km 8.5 min';

    public function apply(string $text, array $parsed): RuleResult
    {
        $hollow = ($parsed['log_type'] ?? null) === LogType::Workout->value
            && ($parsed['exercises'] ?? []) === []
            && ($parsed['duration_minutes'] ?? null) === null;

        return $hollow ? RuleResult::stop($parsed, self::REPLY) : RuleResult::continue($parsed);
    }
}
