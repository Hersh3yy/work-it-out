<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

/**
 * "4 of those" after a superset means every exercise in it was done four
 * times. When the reader put the rounds on one exercise only, or on none,
 * the group's count is the most rounds any of its exercises has.
 */
final class RoundsCountForEveryExerciseInTheGroup implements InterpretationRule
{
    public function apply(string $text, array $parsed): RuleResult
    {
        $rounds = [];

        foreach ($parsed['exercises'] ?? [] as $exercise) {
            $group = $exercise['group'] ?? null;

            if ($group !== null) {
                $rounds[$group] = max($rounds[$group] ?? 0, (int) ($exercise['sets'] ?? 0));
            }
        }

        foreach ($parsed['exercises'] ?? [] as $index => $exercise) {
            $group = $exercise['group'] ?? null;

            if ($group !== null && ($rounds[$group] ?? 0) > 0 && ($exercise['set_list'] ?? []) === []) {
                $parsed['exercises'][$index]['sets'] = $rounds[$group];
            }
        }

        return RuleResult::continue($parsed);
    }
}
