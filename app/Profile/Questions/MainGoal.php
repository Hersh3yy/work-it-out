<?php

declare(strict_types=1);

namespace App\Profile\Questions;

use App\Enums\PrimaryGoal;
use App\Models\User;

/**
 * The user's main goal, one of the PrimaryGoal options. The option's number
 * or a short answer naming it picks the option.
 */
final class MainGoal implements ProfileQuestion
{
    private const int MAX_WORDS = 3;

    /** @var array<string, PrimaryGoal> option number or keyword => goal */
    private const array CHOICES = [
        '1' => PrimaryGoal::BuildMuscle,
        '2' => PrimaryGoal::LoseFat,
        '3' => PrimaryGoal::ImproveEndurance,
        '4' => PrimaryGoal::GeneralFitness,
        'muscle' => PrimaryGoal::BuildMuscle,
        'bulk' => PrimaryGoal::BuildMuscle,
        'fat' => PrimaryGoal::LoseFat,
        'lose' => PrimaryGoal::LoseFat,
        'cut' => PrimaryGoal::LoseFat,
        'endurance' => PrimaryGoal::ImproveEndurance,
        'cardio' => PrimaryGoal::ImproveEndurance,
        'run' => PrimaryGoal::ImproveEndurance,
        'general' => PrimaryGoal::GeneralFitness,
        'fitness' => PrimaryGoal::GeneralFitness,
        'health' => PrimaryGoal::GeneralFitness,
    ];

    public function key(): string
    {
        return 'primary_goal';
    }

    public function isAnswered(User $user): bool
    {
        return $user->primary_goal !== null;
    }

    public function ask(User $user): string
    {
        return "What's your main goal right now? 1 build muscle, 2 lose fat, 3 endurance, 4 general fitness (or skip)";
    }

    public function answer(User $user, string $text): ?string
    {
        $goal = $this->goalIn(mb_strtolower(trim($text, " \t\n\r.!")));

        if ($goal === null) {
            return null;
        }

        $user->update(['primary_goal' => $goal]);

        return 'Saved: your main goal is '.mb_strtolower($goal->label()).'.';
    }

    /**
     * The option's number, or a few words without numbers that name a goal
     * ("lose fat", "build muscle mostly"). "run 5k in 25 min" is a log.
     */
    private function goalIn(string $text): ?PrimaryGoal
    {
        if (preg_match('/^[1-4]$/', $text)) {
            return self::CHOICES[$text];
        }

        $words = preg_split('/[\s,]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) > self::MAX_WORDS || preg_match('/\d/', $text)) {
            return null;
        }

        foreach ($words as $word) {
            if (isset(self::CHOICES[$word])) {
                return self::CHOICES[$word];
            }
        }

        return null;
    }
}
