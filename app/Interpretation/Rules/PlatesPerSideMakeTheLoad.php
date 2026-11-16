<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

use App\Interpretation\ExerciseNotes;
use App\Services\Exercises\ExerciseLine;

/**
 * "11.25 kg a side" is 22.5 kg on the bar, the bar itself not counted
 * because its weight was not said. Doubled here, not by the reader.
 */
final class PlatesPerSideMakeTheLoad implements InterpretationRule
{
    public function apply(string $text, array $parsed): RuleResult
    {
        foreach ($parsed['exercises'] ?? [] as $index => $exercise) {
            $perSide = $exercise['per_side_kg'] ?? null;

            if (! is_numeric($perSide) || ($exercise['weight_kg'] ?? null) !== null) {
                continue;
            }

            $exercise['weight_kg'] = round((float) $perSide * 2, 2);
            $parsed['exercises'][$index] = ExerciseNotes::add($exercise, ExerciseLine::number($perSide).' kg a side, bar not counted');
        }

        return RuleResult::continue($parsed);
    }
}
