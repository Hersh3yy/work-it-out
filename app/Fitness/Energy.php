<?php

declare(strict_types=1);

namespace App\Fitness;

use App\Enums\TrainerPersona;
use LogicException;

/**
 * The three kinds of fitness a person trains, one coach each: the one that
 * lifts, the one that lasts, the one that heals. Plain words here; a theme
 * may name them differently at the edge.
 */
enum Energy: string
{
    case Lift = 'lift';
    case Last = 'last';
    case Heal = 'heal';

    public function description(): string
    {
        return match ($this) {
            self::Lift => 'the one that lifts',
            self::Last => 'the one that lasts',
            self::Heal => 'the one that heals',
        };
    }

    /**
     * @return list<Quality>
     */
    public function qualities(): array
    {
        return array_values(array_filter(Quality::cases(), fn (Quality $quality): bool => $quality->energy() === $this));
    }

    public function coach(): TrainerPersona
    {
        foreach (TrainerPersona::cases() as $coach) {
            if ($coach->energy() === $this) {
                return $coach;
            }
        }

        throw new LogicException("No coach owns the {$this->value} energy.");
    }
}
