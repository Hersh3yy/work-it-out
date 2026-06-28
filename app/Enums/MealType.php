<?php

declare(strict_types=1);

namespace App\Enums;

enum MealType: string
{
    case Breakfast  = 'breakfast';
    case Lunch      = 'lunch';
    case Dinner     = 'dinner';
    case Snack      = 'snack';
    case Supplement = 'supplement';

    public function label(): string
    {
        return match ($this) {
            self::Breakfast  => 'Breakfast',
            self::Lunch      => 'Lunch',
            self::Dinner     => 'Dinner',
            self::Snack      => 'Snack',
            self::Supplement => 'Supplement',
        };
    }
}
