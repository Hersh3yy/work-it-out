<?php

declare(strict_types=1);

namespace App\Interpretation;

use App\Enums\MessageKind;

/**
 * The classify step without AI: the obvious kinds, decided by plain rules.
 * Returns null when it is not sure, so the next classifier in line decides.
 */
final class RuleClassifier
{
    private const string WEIGHT_WITH_UNIT = '/^\s*(?:(?:i\s+)?weigh(?:ed)?\s+|weight\s*:?\s*|gewicht\s*:?\s*)?\d{2,3}(?:[.,]\d+)?\s*(?:kg|kgs|kilo|kilos)\s*$/iu';

    public function classify(string $text): ?Classification
    {
        $text = trim($text);

        if (str_starts_with($text, '/')) {
            return new Classification(MessageKind::Command, 1.0, 'rules');
        }

        if (preg_match(self::WEIGHT_WITH_UNIT, $text)) {
            return new Classification(MessageKind::BodyWeight, 1.0, 'rules');
        }

        return null;
    }
}
