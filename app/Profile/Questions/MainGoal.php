<?php

declare(strict_types=1);

namespace App\Profile\Questions;

use App\Enums\PrimaryGoal;
use App\Models\User;

/**
 * The user's main goal, one of the PrimaryGoal options. A number or a word
 * from the answer picks the option.
 */
final class MainGoal implements ProfileQuestion
{
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
        $text = mb_strtolower(trim($text));

        foreach (self::CHOICES as $word => $goal) {
            if (preg_match('/(^|\b)'.preg_quote((string) $word, '/').'(\b|$)/', $text)) {
                $user->update(['primary_goal' => $goal]);

                return 'Saved: your main goal is '.mb_strtolower($goal->label()).'.';
            }
        }

        return null;
    }
}
