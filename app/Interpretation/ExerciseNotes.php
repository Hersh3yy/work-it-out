<?php

declare(strict_types=1);

namespace App\Interpretation;

/**
 * A note the rules add to a parsed exercise ("185 lb", "20 kg a side, bar not
 * counted"), after what the reader already wrote.
 */
final class ExerciseNotes
{
    /**
     * @param  array<string, mixed>  $exercise
     * @return array<string, mixed>
     */
    public static function add(array $exercise, string $note): array
    {
        $existing = trim((string) ($exercise['notes'] ?? ''));

        $exercise['notes'] = $existing === '' ? $note : (str_contains($existing, $note) ? $existing : "{$existing}, {$note}");

        return $exercise;
    }
}
