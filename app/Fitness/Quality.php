<?php

declare(strict_types=1);

namespace App\Fitness;

use App\Fitness\Measures\CorePower;
use App\Fitness\Measures\EndurancePower;
use App\Fitness\Measures\NoTestYet;
use App\Fitness\Measures\PowerMeasure;
use App\Fitness\Measures\RecoveryPower;
use App\Fitness\Measures\StrengthPower;

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

    public function powerMeasure(): PowerMeasure
    {
        return match ($this) {
            self::Strength => new StrengthPower,
            self::Endurance => new EndurancePower,
            self::Core => new CorePower,
            self::Recovery => new RecoveryPower,
            self::Power, self::Mobility => new NoTestYet,
        };
    }
}
