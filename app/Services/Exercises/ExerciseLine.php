<?php

declare(strict_types=1);

namespace App\Services\Exercises;

use App\Models\ExerciseEntry;
use App\Models\ExerciseSet;
use Illuminate\Support\Collection;

/**
 * One saved exercise as a line of text, the same words everywhere it is
 * shown: the chat receipt, /undo, the coach's context, the profile writer.
 * "Bench Press 5x5 @ 80, 80, 75, 75, 75 kg", "Push-ups 4x10 (bodyweight)",
 * "Plank 4x40s", "Assisted Pull-up 5x5, 45 kg help", "Run 1 km 6:30".
 */
final readonly class ExerciseLine
{
    public function for(ExerciseEntry $entry): string
    {
        $sets = $entry->setRows()->get();

        if ($sets->isNotEmpty() && self::differ($sets)) {
            return self::join([$entry->exercise_name, self::setLine($sets)], [self::help($entry)]);
        }

        $one = $sets->first();

        return self::join([
            $entry->exercise_name,
            self::work(
                $sets->isNotEmpty() ? $sets->count() : $entry->sets,
                $one->reps ?? $entry->reps,
                $one->duration_seconds ?? ($sets->isEmpty() ? $entry->duration_seconds : null),
                $one->distance_meters ?? ($sets->isEmpty() ? $entry->distance_meters : null),
            ),
            self::load($entry),
        ], [self::help($entry), $one === null ? null : self::effort($one)]);
    }

    /**
     * The words of the line, then what follows a comma: "Squat 5x5 @ 100 kg, 2 in reserve".
     *
     * @param  list<string|null>  $words
     * @param  list<string|null>  $afterComma
     */
    private static function join(array $words, array $afterComma): string
    {
        return implode(', ', array_filter([implode(' ', array_filter($words)), ...$afterComma]));
    }

    /** "80.5", never "80.50" or "80.00". */
    public static function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * "3x8", "10 reps", "4x40s", "8x400 m", "5 km 28:00", "4 sets".
     */
    private static function work(?int $count, ?int $reps, ?int $seconds, ?int $meters): ?string
    {
        $each = array_filter([
            $reps === null ? null : (string) $reps,
            $meters === null ? null : self::distance($meters),
            $seconds === null ? null : self::duration($seconds),
        ]);

        if ($each === []) {
            return $count === null ? null : "{$count} sets";
        }

        $each = implode(' ', $each);

        return match (true) {
            $count === null || $count === 1 && $reps === null => $each,
            $count === 1 => "{$reps} reps",
            default => "{$count}x{$each}",
        };
    }

    /** "@ 80 kg" or "(bodyweight)"; help comes after the comma. */
    private static function load(ExerciseEntry $entry): ?string
    {
        return match (true) {
            $entry->assisted => null,
            $entry->weight_kg !== null => '@ '.self::number($entry->weight_kg).' kg',
            $entry->bodyweight && $entry->reps !== null => '(bodyweight)',
            default => null,
        };
    }

    private static function help(ExerciseEntry $entry): ?string
    {
        return $entry->assisted && $entry->weight_kg !== null ? self::number($entry->weight_kg).' kg help' : null;
    }

    /** "RPE 8", "2 in reserve", "to failure". */
    private static function effort(ExerciseSet $set): ?string
    {
        return match (true) {
            $set->rir === 0 => 'to failure',
            $set->rir !== null => "{$set->rir} in reserve",
            $set->rpe !== null => 'RPE '.self::number($set->rpe),
            default => null,
        };
    }

    /**
     * @param  Collection<int, ExerciseSet>  $sets
     */
    private static function differ(Collection $sets): bool
    {
        foreach (ExerciseSet::VALUES as $column) {
            if ($sets->pluck($column)->unique()->count() > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * "5x5 @ 90, 80, 80, 80, 80 kg" when only the weights differ;
     * "80 kg × 8, 8, 6 (to failure)" when only the reps or the effort differ;
     * "90×5, 80×4, 80×3 kg" when both do; "40s, 45s, 30s" for timed sets.
     *
     * @param  Collection<int, ExerciseSet>  $sets
     */
    private static function setLine(Collection $sets): string
    {
        $weight = static fn (ExerciseSet $set): string => $set->weight_kg === null ? '?' : self::number($set->weight_kg);
        $reps = $sets->pluck('reps')->unique();
        $weights = $sets->pluck('weight_kg')->unique();

        if ($reps->first() === null && $weights->first() === null) {
            return $sets->map(static fn (ExerciseSet $set): string => implode(' ', array_filter([
                $set->distance_meters === null ? null : self::distance($set->distance_meters),
                $set->duration_seconds === null ? null : self::duration($set->duration_seconds),
                self::effort($set),
            ])))->implode(', ');
        }

        if ($reps->count() === 1 && $weights->count() > 1) {
            return sprintf('%dx%s @ %s kg', $sets->count(), $reps->first() ?? '?', $sets->map($weight)->implode(', '));
        }

        if ($weights->count() === 1) {
            $each = $sets->map(static fn (ExerciseSet $set): string => ($set->reps ?? '?').self::effortTag($set))->implode(', ');

            return $weights->first() === null ? "{$each} reps" : sprintf('%s kg × %s', self::number($weights->first()), $each);
        }

        return $sets->map(static fn (ExerciseSet $set): string => $weight($set).'×'.($set->reps ?? '?').self::effortTag($set))->implode(', ').' kg';
    }

    /** " (to failure)", " (RPE 8)" after one set's count. */
    private static function effortTag(ExerciseSet $set): string
    {
        $effort = self::effort($set);

        return $effort === null ? '' : " ({$effort})";
    }

    private static function distance(int $meters): string
    {
        return $meters >= 1000 ? self::number($meters / 1000).' km' : "{$meters} m";
    }

    private static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        if ($seconds % 60 === 0) {
            return intdiv($seconds, 60).' min';
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
