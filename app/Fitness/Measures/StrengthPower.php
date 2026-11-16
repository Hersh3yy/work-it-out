<?php

declare(strict_types=1);

namespace App\Fitness\Measures;

use App\Fitness\PowerReading;
use App\Fitness\TrainingHistory;
use App\Models\ExerciseEntry;
use App\Models\ExerciseSet;
use App\Services\Exercises\ExerciseAliases;

/**
 * The best estimated one-rep max of a main lift, per kilo of body weight,
 * against that lift's standard.
 */
final readonly class StrengthPower implements PowerMeasure
{
    public function measure(TrainingHistory $history): ?PowerReading
    {
        if ($history->bodyWeightKg === null) {
            return null;
        }

        $best = null;

        foreach ($history->within((int) config('fitness.power.strength_window_days')) as $entry) {
            $standard = $this->standardFor($entry->exercise_name);
            $oneRepMax = $this->bestOneRepMax($entry);

            if ($standard === null || $oneRepMax === null) {
                continue;
            }

            $reading = $this->reading($entry->exercise_name, $oneRepMax, $history->bodyWeightKg, $standard);
            $best = $best === null || $reading->value > $best->value ? $reading : $best;
        }

        return $best;
    }

    private function reading(string $exercise, float $oneRepMax, float $bodyWeightKg, float $standard): PowerReading
    {
        $perBodyWeight = $oneRepMax / $bodyWeightKg;

        return PowerReading::against($perBodyWeight, $standard, [
            'exercise' => $exercise,
            'e1rm_kg' => round($oneRepMax, 1),
            'body_weight_kg' => $bodyWeightKg,
            'e1rm_per_body_weight' => round($perBodyWeight, 2),
            'standard' => $standard,
        ]);
    }

    private function standardFor(string $exercise): ?float
    {
        foreach (config('fitness.power.strength_standards') as $lift => $standard) {
            if (ExerciseAliases::key($lift) === ExerciseAliases::key($exercise)) {
                return (float) $standard;
            }
        }

        return null;
    }

    private function bestOneRepMax(ExerciseEntry $entry): ?float
    {
        if (! $entry->isLoaded()) {
            return null;
        }

        $sets = $entry->setRows->isNotEmpty()
            ? $entry->setRows->map(static fn (ExerciseSet $set): array => [(int) $set->reps, (float) $set->weight_kg])
            : collect([[(int) $entry->reps, (float) $entry->weight_kg]]);

        return $sets
            ->filter(static fn (array $set): bool => $set[0] >= 1 && $set[0] <= (int) config('fitness.power.epley_max_reps') && $set[1] > 0)
            ->map(static fn (array $set): float => OneRepMax::epley($set[1], $set[0]))
            ->max();
    }
}
