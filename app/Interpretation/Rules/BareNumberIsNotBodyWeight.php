<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

/**
 * A body weight is only a body weight when the message says so: a unit or a
 * word like "weigh". "90 90 95 85 85" is never written to the profile as 90 kg.
 */
final class BareNumberIsNotBodyWeight implements InterpretationRule
{
    private const string SAYS_WEIGHT = '/\d\s*(?:kg|kgs|kilos?|lbs?|pounds?)\b|\b(?:weigh|weighed|weight|gewicht|weeg|woog)\b/iu';

    public function apply(string $text, array $parsed): RuleResult
    {
        if (($parsed['log_type'] ?? null) !== 'biometrics' || preg_match(self::SAYS_WEIGHT, $text)) {
            return RuleResult::continue($parsed);
        }

        return RuleResult::stop($parsed, sprintf(
            'Is %s your body weight, or the weights of your sets? Send it again as "%skg" for your weight, or with the exercise ("squat %s").',
            self::firstNumber($text),
            self::firstNumber($text),
            trim((string) preg_replace('/\s+/', ' ', $text)),
        ));
    }

    private static function firstNumber(string $text): string
    {
        return preg_match('/\d+(?:[.,]\d+)?/', $text, $m) ? $m[0] : 'that';
    }
}
