<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanType: string
{
    case Workout = 'workout';
    case Meal = 'meal';
}
