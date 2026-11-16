<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

/**
 * "10 pushups" is complete: an exercise done with the body alone carries no
 * weight and is never asked for one.
 */
final class BodyweightExerciseNeedsNoWeight implements InterpretationRule
{
    public function apply(string $text, array $parsed): RuleResult
    {
        foreach ($parsed['exercises'] ?? [] as $index => $exercise) {
            if (! ($exercise['bodyweight'] ?? false)) {
                continue;
            }

            $parsed['exercises'][$index]['weight_kg'] = null;
            $parsed['questions'] = array_values(array_filter(
                $parsed['questions'] ?? [],
                static fn (array $question): bool => $question['field'] !== "exercises.{$index}.weight_kg",
            ));
        }

        return RuleResult::continue($parsed);
    }
}
