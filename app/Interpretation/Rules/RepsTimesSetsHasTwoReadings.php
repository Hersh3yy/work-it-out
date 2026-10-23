<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

/**
 * "90 x3 x5" or "2x110kg x 5 times" can be 3 sets of 5 or 5 sets of 3. When the two counts differ,
 * the sets and reps are left empty and the user is asked; the answer (the
 * number of sets) fills both, because the other count is the reps.
 */
final class RepsTimesSetsHasTwoReadings implements InterpretationRule
{
    private const string WEIGHT_TIMES_TIMES = '/\d+(?:[.,]\d+)?\s*(?:kg|kh|kilo)?\s*[x×*]\s*(\d+)\s*[x×*]\s*(\d+)/iu';

    /** "2x110kg x 5 times": a count, the weight with a unit, another count. */
    private const string TIMES_WEIGHT_TIMES = '/(\d+)\s*[x×*]\s*\d+(?:[.,]\d+)?\s*(?:kg|kh|kilo|kilos)\s*[x×*]\s*(\d+)/iu';

    public function apply(string $text, array $parsed): RuleResult
    {
        foreach (self::pairs($text) as $pair) {
            $sorted = $pair;
            sort($sorted);

            foreach ($parsed['exercises'] ?? [] as $index => $exercise) {
                $counts = [(int) ($exercise['sets'] ?? 0), (int) ($exercise['reps'] ?? 0)];
                sort($counts);

                if ($counts !== $sorted) {
                    continue;
                }

                $parsed['exercises'][$index]['sets'] = null;
                $parsed['exercises'][$index]['reps'] = null;
                $parsed['exercises'][$index]['set_list'] = [];
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
        }

        return RuleResult::continue($parsed);
    }

    /**
     * Every count pair in the message that has two readings, in order.
     *
     * @return list<array{0: int, 1: int}>
     */
    private static function pairs(string $text): array
    {
        $pairs = [];

        foreach ([self::WEIGHT_TIMES_TIMES, self::TIMES_WEIGHT_TIMES] as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $m) {
                if ($m[1][0] !== $m[2][0]) {
                    $pairs[$m[0][1]] = [(int) $m[1][0], (int) $m[2][0]];
                }
            }
        }

        ksort($pairs);

        return array_values($pairs);
    }
}
