<?php

declare(strict_types=1);

namespace App\Goals;

/**
 * Finds a goal in a goal-or-info message without a model: a goal word and
 * either a day or words that point ahead ("want", "by", "ready for").
 * "I sleep badly" has no goal word; "the rave was great" no day or intent.
 */
final class GoalReader
{
    private const string POINTS_AHEAD = '/\b(want|wanna|goal|aim|plan|planning|going to|gonna|have a|got a|prepar\w*|train for|training for|ready for|coming up|next|by|before|able to|hope)\b/i';

    public static function read(string $text): ?GoalReading
    {
        $word = GoalKind::in($text);
        $date = GoalDate::in($text);

        if ($word === null || ($date === null && preg_match(self::POINTS_AHEAD, $text) !== 1)) {
            return null;
        }

        return new GoalReading($word->kind, $word->name, mb_substr(trim($text), 0, 255), $date);
    }
}
