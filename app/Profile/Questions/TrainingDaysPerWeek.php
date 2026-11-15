<?php

declare(strict_types=1);

namespace App\Profile\Questions;

use App\Jobs\UpdateUserStats;
use App\Models\User;

/**
 * How many days a week the user plans to train: the denominator of the weekly
 * score, which reads 0% until it is known.
 */
final class TrainingDaysPerWeek implements ProfileQuestion
{
    private const array WORDS = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7];

    /** "4", "four", "4 days", "3x a week"; "3 sets of squats at 100" is a log. */
    private const string WHOLE_ANSWER = '/^(?<days>[1-7]|one|two|three|four|five|six|seven)(?:\s*(?:days?|x|times))?(?:\s*(?:a|per|each)\s+week)?$/';

    public function key(): string
    {
        return 'training_days_per_week';
    }

    public function isAnswered(User $user): bool
    {
        return (int) $user->training_days_per_week > 0;
    }

    public function ask(User $user): string
    {
        return 'Quick one for your weekly score: how many days a week do you plan to train? (1 to 7, or skip)';
    }

    public function answer(User $user, string $text): ?string
    {
        $days = preg_match(self::WHOLE_ANSWER, mb_strtolower(trim($text, " \t\n\r.!")), $m) ? $this->days($m['days']) : null;

        if ($days === null) {
            return null;
        }

        $user->update(['training_days_per_week' => $days]);
        UpdateUserStats::dispatch($user);

        return "Saved: you plan to train {$days} ".($days === 1 ? 'day' : 'days').' a week.';
    }

    private function days(string $number): int
    {
        return self::WORDS[$number] ?? (int) $number;
    }
}
