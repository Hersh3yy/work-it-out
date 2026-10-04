<?php

declare(strict_types=1);

namespace App\Services\Exercises;

/**
 * One name per exercise, so "bench", "Bench Press" and "benchpress" are one
 * exercise with one personal record.
 *
 * Names compare by key: lowercase, letters and digits only. A known alias maps
 * to its canonical name; otherwise the user's own earlier spelling of the same
 * key wins; otherwise the name is kept as written.
 */
final class ExerciseAliases
{
    /** @var array<string, string> key => canonical name */
    private const array ALIASES = [
        'bench' => 'Bench Press',
        'benchpress' => 'Bench Press',
        'bp' => 'Bench Press',
        'flatbench' => 'Bench Press',
        'barbellbenchpress' => 'Bench Press',
        'squat' => 'Squat',
        'squats' => 'Squat',
        'backsquat' => 'Squat',
        'backsquats' => 'Squat',
        'deadlift' => 'Deadlift',
        'deadlifts' => 'Deadlift',
        'dl' => 'Deadlift',
        'rdl' => 'Romanian Deadlift',
        'rdls' => 'Romanian Deadlift',
        'romaniandeadlift' => 'Romanian Deadlift',
        'ohp' => 'Overhead Press',
        'overheadpress' => 'Overhead Press',
        'militarypress' => 'Overhead Press',
        'pullup' => 'Pull-up',
        'pullups' => 'Pull-up',
        'chinup' => 'Chin-up',
        'chinups' => 'Chin-up',
        'legpress' => 'Leg Press',
        'latpulldown' => 'Lat Pulldown',
        'latpulldowns' => 'Lat Pulldown',
        'pulldown' => 'Lat Pulldown',
        'pulldowns' => 'Lat Pulldown',
        'barbellrow' => 'Barbell Row',
        'barbellrows' => 'Barbell Row',
        'bentoverrow' => 'Barbell Row',
        'bentoverrows' => 'Barbell Row',
        'run' => 'Run',
        'running' => 'Run',
        'jog' => 'Run',
        'jogging' => 'Run',
    ];

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

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        foreach ($knownNames as $known) {
            if (self::key($known) === $key) {
                return $known;
            }
        }

        return $written;
    }
}
