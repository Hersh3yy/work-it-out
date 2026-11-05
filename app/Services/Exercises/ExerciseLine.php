<?php

declare(strict_types=1);

namespace App\Services\Exercises;

use App\Models\ExerciseEntry;
use App\Models\ExerciseSet;
use Illuminate\Support\Collection;

/**
 * One saved exercise as a line of text, the same words everywhere it is
 * shown: the chat receipt, /undo, the coach's context, the profile writer.
 * "Bench Press 5x5 @ 80, 80, 75, 75, 75 kg", "Run 1 km 6:30".
 */
final readonly class ExerciseLine
{
    public function for(ExerciseEntry $entry): string
    {
        $parts = [$entry->exercise_name];
        $sets = $entry->setRows()->get();

        if ($sets->count() > 0 && ($sets->pluck('reps')->unique()->count() > 1 || $sets->pluck('weight_kg')->unique()->count() > 1)) {
            $parts[] = self::setLine($sets);

            return implode(' ', array_filter($parts));
        }

        $parts[] = match (true) {
            $entry->sets !== null && $entry->reps !== null => "{$entry->sets}x{$entry->reps}",
            $entry->reps !== null => "{$entry->reps} reps",
            $entry->sets !== null => "{$entry->sets} sets",
            default => null,
        };

        if ($entry->weight_kg !== null) {
            $parts[] = '@ '.self::number($entry->weight_kg).' kg';
        }
        if ($entry->distance_meters !== null) {
            $parts[] = self::number($entry->distance_meters / 1000).' km';
        }
        if ($entry->duration_seconds !== null) {
            $parts[] = self::duration((int) $entry->duration_seconds);
        }

        return implode(' ', array_filter($parts));
    }

    /** "80.5", never "80.50" or "80.00". */
    public static function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * "5x5 @ 90, 80, 80, 80, 80 kg" when only the weights differ;
     * "90×5, 80×4, 80×3 kg" when the reps differ too.
     *
     * @param  Collection<int, ExerciseSet>  $sets
     */
    private static function setLine(Collection $sets): string
    {
        $weight = static fn ($set): string => $set->weight_kg === null ? '?' : self::number($set->weight_kg);
        $reps = $sets->pluck('reps')->unique();

        if ($reps->count() === 1) {
            return sprintf('%dx%s @ %s kg', $sets->count(), $reps->first() ?? '?', $sets->map($weight)->implode(', '));
        }

        return $sets->map(fn ($set): string => $weight($set).'×'.($set->reps ?? '?'))->implode(', ').' kg';
    }

    private static function duration(int $seconds): string
    {
        if ($seconds % 60 === 0) {
            return intdiv($seconds, 60).' min';
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
