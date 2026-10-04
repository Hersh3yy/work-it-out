<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

/**
 * "90 x3 x5" can be 3 sets of 5 or 5 sets of 3. When the two counts differ,
 * the sets and reps are left empty and the user is asked; the answer (the
 * number of sets) fills both, because the other count is the reps.
 */
final class RepsTimesSetsHasTwoReadings implements InterpretationRule
{
    private const string WEIGHT_TIMES_TIMES = '/\d+(?:[.,]\d+)?\s*(?:kg|kilo)?\s*[x×*]\s*(\d+)\s*[x×*]\s*(\d+)/iu';

    public function apply(string $text, array $parsed): RuleResult
    {
        if (! preg_match(self::WEIGHT_TIMES_TIMES, $text, $m) || $m[1] === $m[2]) {
            return RuleResult::continue($parsed);
        }

        $pair = [(int) $m[1], (int) $m[2]];

        foreach ($parsed['exercises'] ?? [] as $index => $exercise) {
            $counts = [(int) ($exercise['sets'] ?? 0), (int) ($exercise['reps'] ?? 0)];
            sort($counts);
            $sorted = $pair;
            sort($sorted);

            if ($counts !== $sorted) {
                continue;
            }

            $parsed['exercises'][$index]['sets'] = null;
            $parsed['exercises'][$index]['reps'] = null;
            $parsed['questions'] = [[
                'field' => "exercises.{$index}.sets",
                'question' => sprintf(
                    'Was that %d sets of %d reps, or %d sets of %d reps? Reply with the number of sets.',
                    $pair[0], $pair[1], $pair[1], $pair[0],
                ),
                'pair' => $pair,
            ]];

            return RuleResult::continue($parsed);
        }

        return RuleResult::continue($parsed);
    }
}
