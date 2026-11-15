<?php

declare(strict_types=1);

namespace App\Fitness\Measures;

use App\Fitness\PowerReading;
use App\Fitness\Quality;
use App\Fitness\TrainingHistory;
use App\Models\ExerciseEntry;

/**
 * Cardio minutes this week against the weekly guideline, with the longest
 * run of the month alongside (the base for the next run's cap).
 */
final readonly class EndurancePower implements PowerMeasure
{
    public function measure(TrainingHistory $history): ?PowerReading
    {
        $month = $history->training(Quality::Endurance, (int) config('fitness.power.longest_run_window_days'));

        if ($month->isEmpty()) {
            return null;
        }

        $minutes = $history->training(Quality::Endurance, (int) config('fitness.power.cardio_window_days'))->sum($this->minutes(...));
        $longestRun = $month->max(static fn (ExerciseEntry $entry): int => (int) $entry->distance_meters);

        return PowerReading::against($minutes, (float) config('fitness.power.cardio_minutes_guideline'), [
            'cardio_minutes_7d' => $minutes,
            'guideline_minutes' => (int) config('fitness.power.cardio_minutes_guideline'),
            'longest_run_30d_m' => $longestRun > 0 ? $longestRun : null,
        ]);
    }

    /** The entry's own time, else its session's when that was the whole session. */
    private function minutes(ExerciseEntry $entry): int
    {
        if ((int) $entry->duration_seconds > 0) {
            return (int) round($entry->duration_seconds / 60);
        }

        return $entry->workoutSession->exerciseEntries->count() === 1 ? (int) $entry->workoutSession->duration_minutes : 0;
    }
}
