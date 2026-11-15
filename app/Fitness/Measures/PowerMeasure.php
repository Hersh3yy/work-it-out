<?php

declare(strict_types=1);

namespace App\Fitness\Measures;

use App\Fitness\PowerReading;
use App\Fitness\TrainingHistory;

/**
 * How one quality's power is measured from a person's history. Null when
 * there is nothing to measure it from: unknown is never zero.
 *
 * @see https://refactoring.guru/design-patterns/strategy
 */
interface PowerMeasure
{
    public function measure(TrainingHistory $history): ?PowerReading;
}
