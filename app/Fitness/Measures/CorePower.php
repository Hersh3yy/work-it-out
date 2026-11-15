<?php

declare(strict_types=1);

namespace App\Fitness\Measures;

use App\Fitness\PowerReading;
use App\Fitness\Quality;
use App\Fitness\TrainingHistory;
use App\Models\ExerciseEntry;

/**
 * The longest timed hold and the heaviest carry, each against its standard;
 * the one further along is the power.
 */
final readonly class CorePower implements PowerMeasure
{
    public function measure(TrainingHistory $history): ?PowerReading
    {
        $core = $history->training(Quality::Core, (int) config('fitness.power.core_window_days'));
        $hold = (int) $core->reject($this->isCarry(...))->max('duration_seconds');
        $carry = (float) $core->filter($this->isCarry(...))->max('weight_kg');

        if ($hold === 0 && $carry <= 0.0) {
            return null;
        }

        $holdShare = $hold / (int) config('fitness.power.core_hold_standard_seconds');
        $carryShare = $history->bodyWeightKg === null ? 0.0 : $carry / $history->bodyWeightKg / (float) config('fitness.power.carry_per_hand_standard');

        return PowerReading::against(max($holdShare, $carryShare), 1.0, [
            'longest_hold_seconds' => $hold > 0 ? $hold : null,
            'heaviest_carry_kg' => $carry > 0 ? $carry : null,
        ]);
    }

    private function isCarry(ExerciseEntry $entry): bool
    {
        return (bool) preg_match('/carry|farmer/i', $entry->exercise_name);
    }
}
