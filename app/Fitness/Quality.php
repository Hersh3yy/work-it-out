<?php

declare(strict_types=1);

namespace App\Fitness;

use App\Enums\BodyArea;
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

    /** Hard work leaves it spent for a day or two; light mobility and recovery work does not. */
    public function tiresWhenTrained(): bool
    {
        return ! in_array($this, [self::Mobility, self::Recovery], true);
    }

    /**
     * The body areas this quality's main work leans on, so a limitation there
     * lowers its readiness. Not every exercise's areas: a rowing machine loads
     * the lower back, yet a back injury does not stop running.
     *
     * @return list<BodyArea>
     */
    public function leansOn(): array
    {
        return match ($this) {
            self::Strength => [BodyArea::LowerBack, BodyArea::Hip, BodyArea::Knee, BodyArea::Shoulder, BodyArea::Chest, BodyArea::UpperBack, BodyArea::Elbow, BodyArea::Wrist],
            self::Power => [BodyArea::LowerBack, BodyArea::Hip, BodyArea::Knee, BodyArea::Ankle, BodyArea::Shoulder, BodyArea::Wrist],
            self::Endurance => [BodyArea::Hip, BodyArea::Knee, BodyArea::Ankle, BodyArea::Foot],
            self::Core => [BodyArea::LowerBack, BodyArea::Hip, BodyArea::Shoulder],
            self::Mobility, self::Recovery => [],
        };
    }
}
