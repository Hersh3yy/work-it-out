<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

/**
 * "Assisted pull up 45kg": on an assisted machine the kg is the help the
 * machine gives, not a load lifted. The name says so even when the reader
 * forgot the flag, and a flagged exercise is never bodyweight.
 */
final class AssistedMachineKgIsHelp implements InterpretationRule
{
    private const string SAYS_ASSISTED = '/\bassist(?:ed|ance)?\b|\bgeassisteerd/iu';

    public function apply(string $text, array $parsed): RuleResult
    {
        foreach ($parsed['exercises'] ?? [] as $index => $exercise) {
            $assisted = ($exercise['assisted'] ?? false)
                || preg_match(self::SAYS_ASSISTED, (string) ($exercise['exercise_name'] ?? ''))
                || preg_match(self::SAYS_ASSISTED, (string) ($exercise['notes'] ?? ''));

            if (! $assisted) {
                continue;
            }

            $parsed['exercises'][$index]['assisted'] = true;
            $parsed['exercises'][$index]['bodyweight'] = false;
        }

        return RuleResult::continue($parsed);
    }
}
