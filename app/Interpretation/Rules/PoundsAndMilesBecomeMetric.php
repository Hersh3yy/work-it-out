<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

use App\Interpretation\ExerciseNotes;
use App\Services\Exercises\ExerciseLine;

/**
 * "185 lb" and "3 miles" are passed on by the reader as said and converted
 * here, so the number in the log is arithmetic the app did, not the model.
 */
final class PoundsAndMilesBecomeMetric implements InterpretationRule
{
    private const float KG_PER_LB = 0.45359237;

    private const float METERS_PER_MILE = 1609.344;

    public function apply(string $text, array $parsed): RuleResult
    {
        foreach ($parsed['exercises'] ?? [] as $index => $exercise) {
            $pounds = $exercise['weight_lb'] ?? null;
            $miles = $exercise['distance_miles'] ?? null;

            if (is_numeric($pounds) && ($exercise['weight_kg'] ?? null) === null) {
                $exercise['weight_kg'] = round((float) $pounds * self::KG_PER_LB, 2);
                $exercise = ExerciseNotes::add($exercise, ExerciseLine::number($pounds).' lb');
            }

            if (is_numeric($miles) && ($exercise['distance_meters'] ?? null) === null) {
                $exercise['distance_meters'] = (int) round((float) $miles * self::METERS_PER_MILE);
                $exercise = ExerciseNotes::add($exercise, ExerciseLine::number($miles).' mi');
            }

            $parsed['exercises'][$index] = $exercise;
        }

        return RuleResult::continue($parsed);
    }
}
