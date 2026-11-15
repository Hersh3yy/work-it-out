<?php

declare(strict_types=1);

namespace App\Fitness\Measures;

use App\Fitness\PowerReading;
use App\Fitness\TrainingHistory;

/** A quality without a test the app can read yet: unscored, never zero. */
final readonly class NoTestYet implements PowerMeasure
{
    public function measure(TrainingHistory $history): ?PowerReading
    {
        return null;
    }
}
