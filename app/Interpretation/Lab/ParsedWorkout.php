<?php

declare(strict_types=1);

namespace App\Interpretation\Lab;

use App\Services\Exercises\ExerciseLine;

/**
 * What the interpreter made of a message on one line, to set beside the
 * shape a catalogue line expects: "Bench Press 3x8 @80; ask: What weight?".
 */
final class ParsedWorkout
{
    /** @var array<string, string> payload key => label before its value */
    private const array VALUES = [
        'weight_kg' => '@', 'weight_lb' => 'lb ', 'per_side_kg' => 'side ', 'duration_seconds' => 's ',
        'distance_meters' => 'm ', 'distance_miles' => 'mi ', 'pace_seconds_per_km' => 'pace ', 'rpe' => 'rpe ', 'rir' => 'rir ',
    ];

    /**
     * @param  array<string, mixed>|null  $parsed
     */
    public static function describe(?array $parsed, ?string $reply): string
    {
        $parts = [($parsed['log_type'] ?? 'nothing').':'];

        foreach ($parsed['exercises'] ?? [] as $exercise) {
            $parts[] = self::exercise($exercise).';';
        }

        if (($parsed['workout_notes'] ?? null) !== null) {
            $parts[] = "notes \"{$parsed['workout_notes']}\";";
        }
        if (($parsed['weight_kg_stat'] ?? null) !== null) {
            $parts[] = 'body '.ExerciseLine::number($parsed['weight_kg_stat']).' kg;';
        }
        if (($parsed['questions'][0]['question'] ?? null) !== null) {
            $parts[] = "ask: {$parsed['questions'][0]['question']}";
        }
        if ($reply !== null) {
            $parts[] = 'reply: '.mb_strimwidth((string) preg_replace('/\s+/', ' ', $reply), 0, 70, '…');
        }

        return rtrim(implode(' ', $parts), ';');
    }

    /**
     * @param  array<string, mixed>  $exercise
     */
    private static function exercise(array $exercise): string
    {
        $parts = [(string) ($exercise['exercise_name'] ?? '?')];

        if (($exercise['sets'] ?? null) !== null || ($exercise['reps'] ?? null) !== null) {
            $parts[] = ($exercise['sets'] ?? '?').'x'.($exercise['reps'] ?? '?');
        }

        foreach (self::VALUES as $key => $label) {
            if (is_numeric($exercise[$key] ?? null)) {
                $parts[] = $label.ExerciseLine::number($exercise[$key]);
            }
        }

        foreach (['bodyweight' => 'bodyweight', 'assisted' => 'assisted'] as $flag => $word) {
            if ($exercise[$flag] ?? false) {
                $parts[] = $word;
            }
        }

        if (($exercise['group'] ?? null) !== null) {
            $parts[] = "[{$exercise['group']}]";
        }
        if (($exercise['set_list'] ?? []) !== []) {
            $parts[] = 'sets('.implode(' ', array_map(self::set(...), $exercise['set_list'])).')';
        }
        if (($exercise['notes'] ?? null) !== null) {
            $parts[] = "\"{$exercise['notes']}\"";
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $set
     */
    private static function set(array $set): string
    {
        $values = array_filter([
            ($set['weight_kg'] ?? null) === null ? null : ExerciseLine::number($set['weight_kg']),
            ($set['reps'] ?? null) === null ? null : 'x'.$set['reps'],
            ($set['duration_seconds'] ?? null) === null ? null : $set['duration_seconds'].'s',
            ($set['distance_meters'] ?? null) === null ? null : ExerciseLine::number($set['distance_meters']).'m',
            ($set['rir'] ?? null) === null ? null : 'rir'.$set['rir'],
            ($set['rpe'] ?? null) === null ? null : 'rpe'.ExerciseLine::number($set['rpe']),
        ], static fn (?string $value): bool => $value !== null);

        return $values === [] ? '-' : implode('', $values);
    }
}
