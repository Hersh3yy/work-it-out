<?php

declare(strict_types=1);

namespace App\Goals;

/** What the time left asks for: build further out, taper and rest close by. */
enum GoalPhase: string
{
    case Build = 'build';
    case Taper = 'taper';

    public static function for(int $daysLeft): self
    {
        return $daysLeft <= (int) config('fitness.goals.taper_days') ? self::Taper : self::Build;
    }
}
