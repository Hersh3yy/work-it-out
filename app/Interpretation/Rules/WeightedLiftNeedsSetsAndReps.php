<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

/**
 * A lift with a weight needs its sets and reps. When one is missing and no
 * other question is open, ask for it instead of saving a half-known lift
 * silently ("2x70kg" could be 2 reps or 2 sets).
 */
final class WeightedLiftNeedsSetsAndReps implements InterpretationRule
{
    public function apply(string $text, array $parsed): RuleResult
    {
        if (($parsed['questions'] ?? []) !== []) {
            return RuleResult::continue($parsed);
        }

        foreach ($parsed['exercises'] ?? [] as $index => $exercise) {
            $listed = is_array($exercise['set_list'] ?? null) ? $exercise['set_list'] : [];
            $weight = $exercise['weight_kg'] ?? (collect($listed)->pluck('weight_kg')->filter()->max());

            if ($weight === null) {
                continue;
            }

            $listedHasReps = $listed !== [] && collect($listed)->every(fn (array $set): bool => ($set['reps'] ?? null) !== null);

            $missing = match (true) {
                ($exercise['reps'] ?? null) === null && ! $listedHasReps => 'reps',
                ($exercise['sets'] ?? null) === null && $listed === [] => 'sets',
                default => null,
            };

            if ($missing === null) {
                continue;
            }

            $parsed['questions'] = [[
                'field' => "exercises.{$index}.{$missing}",
                'question' => sprintf(
                    $missing === 'reps' ? 'How many reps per set for %s at %s kg?' : 'How many sets of %s at %s kg?',
                    $exercise['exercise_name'] ?? 'that exercise',
                    rtrim(rtrim(number_format((float) $weight, 2, '.', ''), '0'), '.'),
                ),
            ]];

            return RuleResult::continue($parsed);
        }

        return RuleResult::continue($parsed);
    }
}
