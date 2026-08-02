<?php

declare(strict_types=1);

namespace App\Contracts\Stats;

use App\Models\User;

/**
 * Port for deriving real personal records from logged workout data.
 *
 * Replaces the previous "random" stats: every number returned here is
 * computed from actual exercise entries (max bench press, best run pace,
 * padel session history, ...), never invented by the AI.
 */
interface PersonalRecords
{
    /**
     * @return array{
     *     strength: list<array{exercise: string, max_weight_kg: float, reps: int|null, achieved_on: string}>,
     *     endurance: array{longest_distance: array{exercise: string, distance_meters: float, achieved_on: string}|null, best_pace: array{exercise: string, seconds_per_km: int, pace_label: string, achieved_on: string}|null},
     *     sports: list<array{name: string, sessions: int, longest_duration_minutes: int|null, last_played_on: string}>
     * }
     */
    public function for(User $user): array;
}
