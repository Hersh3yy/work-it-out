<?php

declare(strict_types=1);

namespace App\Fitness\Measures;

final class OneRepMax
{
    /** Epley: the weight one set of these reps implies for a single rep. */
    public static function epley(float $weightKg, int $reps): float
    {
        return $weightKg * (1 + $reps / 30);
    }
}
