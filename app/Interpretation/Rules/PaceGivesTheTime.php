<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

/**
 * "5k at 6:00/km" took 30 minutes: a pace and a distance give the time,
 * worked out here so the reader never does the sum.
 */
final class PaceGivesTheTime implements InterpretationRule
{
    public function apply(string $text, array $parsed): RuleResult
    {
        foreach ($parsed['exercises'] ?? [] as $index => $exercise) {
            $pace = $exercise['pace_seconds_per_km'] ?? null;
            $meters = $exercise['distance_meters'] ?? null;

            if (! is_numeric($pace) || ! is_numeric($meters) || ($exercise['duration_seconds'] ?? null) !== null) {
                continue;
            }

            $parsed['exercises'][$index]['duration_seconds'] = (int) round((float) $meters / 1000 * (float) $pace);
        }

        return RuleResult::continue($parsed);
    }
}
