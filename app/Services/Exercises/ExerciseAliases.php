<?php

declare(strict_types=1);

namespace App\Services\Exercises;

use App\Fitness\ExerciseCatalogue;

/**
 * One name per exercise, so "bench", "Bench Press" and "benchpress" are one
 * exercise with one personal record.
 *
 * Names compare by key: lowercase, letters and digits only. A name or alias
 * in the exercise catalogue maps to its canonical name; otherwise the user's own earlier spelling of the same
 * key wins; otherwise the name is kept as written.
 */
final class ExerciseAliases
{
    public static function key(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($name));
    }

    /**
     * @param  iterable<string>  $knownNames  names this user has logged before
     */
    public static function canonical(string $name, iterable $knownNames = []): string
    {
        $written = trim((string) preg_replace('/\s+/', ' ', $name));
        $key = self::key($written);

        $known = ExerciseCatalogue::renaming($written);

        if ($known !== null) {
            return $known->name;
        }

        foreach ($knownNames as $known) {
            if (self::key($known) === $key) {
                return $known;
            }
        }

        return $written;
    }
}
