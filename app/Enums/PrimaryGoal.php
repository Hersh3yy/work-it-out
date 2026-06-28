<?php

declare(strict_types=1);

namespace App\Enums;

enum PrimaryGoal: string
{
    case BuildMuscle       = 'build_muscle';
    case LoseFat           = 'lose_fat';
    case ImproveEndurance  = 'improve_endurance';
    case GeneralFitness    = 'general_fitness';

    public function label(): string
    {
        return match ($this) {
            self::BuildMuscle      => 'Build Muscle',
            self::LoseFat          => 'Lose Fat',
            self::ImproveEndurance => 'Improve Endurance',
            self::GeneralFitness   => 'General Fitness',
        };
    }
}
