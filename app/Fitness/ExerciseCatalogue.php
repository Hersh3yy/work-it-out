<?php

declare(strict_types=1);

namespace App\Fitness;

use App\Enums\BodyArea;
use App\Services\Exercises\ExerciseAliases;

/**
 * The one place that knows what an exercise is: its name, what it trains,
 * how it moves and what it loads. A logged name matches an entry when one
 * of the entry's terms is a whole word in it ("walking lunges" is a lunge,
 * "cable crunch" is not a run), or, for terms of six letters or more, sits
 * inside it with the spaces taken out ("legpress", "benchpress").
 */
final class ExerciseCatalogue
{
    private const int JOINED_TERM_LENGTH = 6;

    /** @var list<Exercise>|null */
    private static ?array $exercises = null;

    /**
     * @return list<Exercise>
     */
    public static function all(): array
    {
        return self::$exercises ??= array_map(Exercise::fromConfig(...), config('exercises'));
    }

    /** The exercise whose alias this exact name is, by key: the one that renames it. */
    public static function renaming(string $written): ?Exercise
    {
        $key = ExerciseAliases::key($written);

        foreach (self::all() as $exercise) {
            foreach ($exercise->aliases as $alias) {
                if (ExerciseAliases::key($alias) === $key) {
                    return $exercise;
                }
            }
        }

        return null;
    }

    /**
     * @return list<Exercise>
     */
    public static function matching(string $written): array
    {
        return array_values(array_filter(self::all(), static fn (Exercise $exercise): bool => array_any(
            $exercise->terms(),
            static fn (string $term): bool => self::nameHas($written, $term),
        )));
    }

    /**
     * @return list<Quality>
     */
    public static function qualitiesOf(string $written): array
    {
        $matching = self::matching($written);

        return array_values(array_filter(
            Quality::cases(),
            static fn (Quality $quality): bool => array_any($matching, static fn (Exercise $e): bool => in_array($quality, $e->qualities, true)),
        ));
    }

    public static function trains(string $written, Quality $quality): bool
    {
        return in_array($quality, self::qualitiesOf($written), true);
    }

    /**
     * @return list<BodyArea>
     */
    public static function areasFor(string $written): array
    {
        $areas = [];

        foreach (self::matching($written) as $exercise) {
            $areas = [...$areas, ...$exercise->loads];
        }

        return array_values(array_unique($areas, SORT_REGULAR));
    }

    public static function hasPattern(string $written, MovementPattern $pattern): bool
    {
        return array_any(self::matching($written), static fn (Exercise $e): bool => $e->pattern === $pattern);
    }

    /**
     * @return list<Exercise>
     */
    public static function loading(BodyArea $area): array
    {
        return array_values(array_filter(self::all(), static fn (Exercise $e): bool => in_array($area, $e->loads, true)));
    }

    private static function nameHas(string $written, string $term): bool
    {
        $words = self::words($written);
        $termWords = self::words($term);

        if ($termWords !== '' && preg_match('/(^| )'.preg_quote($termWords, '/').'(s|es)?( |$)/', $words) === 1) {
            return true;
        }

        $termKey = ExerciseAliases::key($term);

        return mb_strlen($termKey) >= self::JOINED_TERM_LENGTH && str_contains(ExerciseAliases::key($written), $termKey);
    }

    private static function words(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(str_replace("'", '', $text))));
    }
}
