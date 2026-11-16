<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

/**
 * A goal without a day cannot be planned toward: the goal is saved and the
 * day asked for, never guessed (2026-10-06: ask while unclear).
 */
final class GoalNeedsADate implements InterpretationRule
{
    public const string FIELD = 'goal.target_on';

    public const string QUESTION = 'When is it? A date like 13 December, or "in 3 weeks".';

    public function apply(string $text, array $parsed): RuleResult
    {
        $goal = $parsed['goal'] ?? null;

        if (! is_array($goal) || $goal['target_on'] !== null) {
            return RuleResult::continue($parsed);
        }

        $parsed['questions'] = [...($parsed['questions'] ?? []), ['field' => self::FIELD, 'question' => self::QUESTION]];

        return RuleResult::continue($parsed);
    }
}
