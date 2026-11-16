<?php

declare(strict_types=1);

namespace App\Limitations;

use App\Enums\BodyArea;
use App\Fitness\Exercise;
use App\Fitness\ExerciseCatalogue;

/**
 * Which body areas an exercise loads, from the exercise catalogue. Plain
 * code, so a logged lift can be checked against an active limitation and
 * the coach told what to leave out, without asking a model.
 */
final class ExerciseLoads
{
    /**
     * @return list<BodyArea>
     */
    public static function areasFor(string $exerciseName): array
    {
        return ExerciseCatalogue::areasFor($exerciseName);
    }

    /**
     * The exercises that usually load an area, in lower case for the coach
     * context: "deadlift, romanian deadlift, squat, ...".
     *
     * @return list<string>
     */
    public static function exercisesLoading(BodyArea $area): array
    {
        return array_map(static fn (Exercise $exercise): string => mb_strtolower($exercise->name), ExerciseCatalogue::loading($area));
    }

    public static function loads(string $exerciseName, BodyArea $area): bool
    {
        return in_array($area, self::areasFor($exerciseName), true);
    }
}
