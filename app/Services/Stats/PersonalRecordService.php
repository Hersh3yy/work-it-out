<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Contracts\Stats\PersonalRecords;
use App\Models\ExerciseEntry;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Collection;

/**
 * Derives real personal records from the user's logged workout data.
 *
 * Everything here is computed — nothing is guessed by an LLM. The output is
 * used three ways: exposed on the API for the frontend, injected into AI
 * coach prompts so reactions reference true numbers, and shown on the
 * dashboard.
 */
final class PersonalRecordService implements PersonalRecords
{
    private const int MAX_STRENGTH_RECORDS = 8;

    private const array RUN_KEYWORDS = ['run', 'jog', 'sprint', 'treadmill', '5k', '10k', 'marathon'];

    private const array SPORT_KEYWORDS = [
        'padel', 'tennis', 'squash', 'badminton', 'football', 'soccer',
        'basketball', 'volleyball', 'climbing', 'boxing', 'swimming', 'cycling',
    ];

    public function for(User $user): array
    {
        $sessions = $user->workoutSessions()->with('exerciseEntries')->get();

        /** @var Collection<int, array{entry: ExerciseEntry, session: WorkoutSession}> $entries */
        $entries = $sessions->flatMap(
            fn (WorkoutSession $session): Collection => $session->exerciseEntries
                ->map(fn (ExerciseEntry $entry): array => ['entry' => $entry, 'session' => $session])
        );

        return [
            'strength' => $this->strengthRecords($entries),
            'endurance' => $this->enduranceRecords($entries),
            'sports' => $this->sportRecords($entries),
        ];
    }

    /**
     * Heaviest lift per exercise (e.g. max bench press), sorted by weight.
     *
     * @param  Collection<int, array{entry: ExerciseEntry, session: WorkoutSession}>  $entries
     * @return list<array{exercise: string, max_weight_kg: float, reps: int|null, achieved_on: string}>
     */
    private function strengthRecords(Collection $entries): array
    {
        return $entries
            ->filter(fn (array $row): bool => (float) ($row['entry']->weight_kg ?? 0) > 0)
            ->groupBy(fn (array $row): string => mb_strtolower(trim($row['entry']->exercise_name)))
            ->map(function (Collection $group): array {
                /** @var array{entry: ExerciseEntry, session: WorkoutSession} $best */
                $best = $group->sortByDesc(fn (array $row): float => (float) $row['entry']->weight_kg)->first();

                return [
                    'exercise' => $best['entry']->exercise_name,
                    'max_weight_kg' => (float) $best['entry']->weight_kg,
                    'reps' => $best['entry']->reps !== null ? (int) $best['entry']->reps : null,
                    'achieved_on' => $best['session']->logged_at->toDateString(),
                ];
            })
            ->sortByDesc('max_weight_kg')
            ->take(self::MAX_STRENGTH_RECORDS)
            ->values()
            ->all();
    }

    /**
     * Longest distance and best pace across running-type entries.
     *
     * @param  Collection<int, array{entry: ExerciseEntry, session: WorkoutSession}>  $entries
     * @return array{longest_distance: array{exercise: string, distance_meters: float, achieved_on: string}|null, best_pace: array{exercise: string, seconds_per_km: int, pace_label: string, achieved_on: string}|null}
     */
    private function enduranceRecords(Collection $entries): array
    {
        $runs = $entries->filter(function (array $row): bool {
            $hasDistance = (float) ($row['entry']->distance_meters ?? 0) > 0;

            return $hasDistance || $this->nameMatches($row['entry']->exercise_name, self::RUN_KEYWORDS);
        });

        $withDistance = $runs->filter(
            fn (array $row): bool => (float) ($row['entry']->distance_meters ?? 0) > 0
        );

        $longest = $withDistance
            ->sortByDesc(fn (array $row): float => (float) $row['entry']->distance_meters)
            ->first();

        // Pace only makes sense for runs of at least 1 km with a duration.
        $paced = $withDistance
            ->filter(fn (array $row): bool => (float) $row['entry']->distance_meters >= 1000
                && (int) ($row['entry']->duration_seconds ?? 0) > 0)
            ->sortBy(fn (array $row): float => (int) $row['entry']->duration_seconds / ((float) $row['entry']->distance_meters / 1000))
            ->first();

        return [
            'longest_distance' => $longest === null ? null : [
                'exercise' => $longest['entry']->exercise_name,
                'distance_meters' => (float) $longest['entry']->distance_meters,
                'achieved_on' => $longest['session']->logged_at->toDateString(),
            ],
            'best_pace' => $paced === null ? null : $this->formatPace($paced),
        ];
    }

    /**
     * Session history per sport (padel, tennis, ...): count, longest, last played.
     *
     * @param  Collection<int, array{entry: ExerciseEntry, session: WorkoutSession}>  $entries
     * @return list<array{name: string, sessions: int, longest_duration_minutes: int|null, last_played_on: string}>
     */
    private function sportRecords(Collection $entries): array
    {
        return $entries
            ->filter(fn (array $row): bool => $this->nameMatches($row['entry']->exercise_name, self::SPORT_KEYWORDS))
            ->groupBy(fn (array $row): string => mb_strtolower(trim($row['entry']->exercise_name)))
            ->map(function (Collection $group): array {
                $durations = $group
                    ->map(fn (array $row): int => $this->durationMinutes($row))
                    ->filter(fn (int $minutes): bool => $minutes > 0);

                /** @var array{entry: ExerciseEntry, session: WorkoutSession} $latest */
                $latest = $group->sortByDesc(fn (array $row): string => $row['session']->logged_at->toIso8601String())->first();

                return [
                    'name' => $latest['entry']->exercise_name,
                    'sessions' => $group->count(),
                    'longest_duration_minutes' => $durations->isEmpty() ? null : $durations->max(),
                    'last_played_on' => $latest['session']->logged_at->toDateString(),
                ];
            })
            ->sortByDesc('sessions')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $keywords
     */
    private function nameMatches(string $exerciseName, array $keywords): bool
    {
        $normalized = mb_strtolower($exerciseName);

        foreach ($keywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Entry duration wins; fall back to the whole session's duration.
     *
     * @param  array{entry: ExerciseEntry, session: WorkoutSession}  $row
     */
    private function durationMinutes(array $row): int
    {
        $seconds = (int) ($row['entry']->duration_seconds ?? 0);

        if ($seconds > 0) {
            return (int) round($seconds / 60);
        }

        return (int) ($row['session']->duration_minutes ?? 0);
    }

    /**
     * @param  array{entry: ExerciseEntry, session: WorkoutSession}  $row
     * @return array{exercise: string, seconds_per_km: int, pace_label: string, achieved_on: string}
     */
    private function formatPace(array $row): array
    {
        $secondsPerKm = (int) round(
            (int) $row['entry']->duration_seconds / ((float) $row['entry']->distance_meters / 1000)
        );

        return [
            'exercise' => $row['entry']->exercise_name,
            'seconds_per_km' => $secondsPerKm,
            'pace_label' => sprintf('%d:%02d /km', intdiv($secondsPerKm, 60), $secondsPerKm % 60),
            'achieved_on' => $row['session']->logged_at->toDateString(),
        ];
    }
}
