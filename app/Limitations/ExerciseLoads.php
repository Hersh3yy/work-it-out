<?php

declare(strict_types=1);

namespace App\Limitations;

use App\Enums\BodyArea;
use App\Services\Exercises\ExerciseAliases;

/**
 * Which body areas an exercise loads, by name keywords. Plain code, so a
 * logged lift can be checked against an active limitation and, later, Shen's
 * suggestions can be filtered without asking a model.
 */
final class ExerciseLoads
{
    /** @var array<string, list<BodyArea>> keyword in the normalized name => areas */
    private const array LOADS = [
        'deadlift' => [BodyArea::LowerBack, BodyArea::Hip],
        'rdl' => [BodyArea::LowerBack, BodyArea::Hip],
        'romanian' => [BodyArea::LowerBack, BodyArea::Hip],
        'squat' => [BodyArea::LowerBack, BodyArea::Knee, BodyArea::Hip],
        'lunge' => [BodyArea::Knee, BodyArea::Hip],
        'legpress' => [BodyArea::Knee, BodyArea::Hip, BodyArea::LowerBack],
        'goodmorning' => [BodyArea::LowerBack],
        'clean' => [BodyArea::LowerBack, BodyArea::Shoulder, BodyArea::Wrist],
        'snatch' => [BodyArea::LowerBack, BodyArea::Shoulder, BodyArea::Wrist],
        'row' => [BodyArea::LowerBack, BodyArea::UpperBack, BodyArea::Elbow],
        'bench' => [BodyArea::Shoulder, BodyArea::Chest, BodyArea::Elbow],
        'press' => [BodyArea::Shoulder, BodyArea::Elbow],
        'dip' => [BodyArea::Shoulder, BodyArea::Chest, BodyArea::Elbow],
        'pullup' => [BodyArea::Shoulder, BodyArea::Elbow, BodyArea::UpperBack],
        'chinup' => [BodyArea::Shoulder, BodyArea::Elbow, BodyArea::UpperBack],
        'pulldown' => [BodyArea::Shoulder, BodyArea::UpperBack],
        'curl' => [BodyArea::Elbow, BodyArea::Wrist],
        'run' => [BodyArea::Knee, BodyArea::Ankle, BodyArea::Foot, BodyArea::Hip],
        'sprint' => [BodyArea::Knee, BodyArea::Ankle, BodyArea::Hip],
        'jump' => [BodyArea::Knee, BodyArea::Ankle],
        'box' => [BodyArea::Knee, BodyArea::Ankle],
    ];

    /**
     * @return list<BodyArea>
     */
    public static function areasFor(string $exerciseName): array
    {
        $key = ExerciseAliases::key($exerciseName);
        $areas = [];

        foreach (self::LOADS as $keyword => $loads) {
            if (str_contains($key, $keyword)) {
                $areas = [...$areas, ...$loads];
            }
        }

        return array_values(array_unique($areas, SORT_REGULAR));
    }

    /** @var array<string, string> keyword => how a person writes it */
    private const array NAMES = [
        'rdl' => 'RDL', 'legpress' => 'leg press', 'goodmorning' => 'good morning',
        'pullup' => 'pull-up', 'chinup' => 'chin-up', 'box' => 'box jump',
    ];

    /**
     * The exercises (as keywords people type) that usually load an area, for
     * the coach context: "deadlift, RDL, squat, good morning, ...".
     *
     * @return list<string>
     */
    public static function exercisesLoading(BodyArea $area): array
    {
        $names = [];

        foreach (self::LOADS as $keyword => $areas) {
            if (in_array($area, $areas, true)) {
                $names[] = self::NAMES[$keyword] ?? $keyword;
            }
        }

        return $names;
    }

    public static function loads(string $exerciseName, BodyArea $area): bool
    {
        return in_array($area, self::areasFor($exerciseName), true);
    }
}
