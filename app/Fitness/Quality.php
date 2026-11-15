<?php

declare(strict_types=1);

namespace App\Fitness;

/**
 * One trainable quality of the body. Each belongs to one energy, two per
 * energy, and has its own level, power and readiness.
 */
enum Quality: string
{
    case Strength = 'strength';
    case Power = 'power';
    case Endurance = 'endurance';
    case Core = 'core';
    case Mobility = 'mobility';
    case Recovery = 'recovery';

    public function energy(): Energy
    {
        return match ($this) {
            self::Strength, self::Power => Energy::Lift,
            self::Endurance, self::Core => Energy::Last,
            self::Mobility, self::Recovery => Energy::Heal,
        };
    }
}
