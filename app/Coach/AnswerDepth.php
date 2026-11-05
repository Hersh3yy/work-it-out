<?php

declare(strict_types=1);

namespace App\Coach;

/**
 * How much room a coach answer gets, decided in plain code from the
 * question (no extra AI call): a quick yes/no or tip, a session or a what
 * to do next, or an explanation of why or how something works. Each depth
 * carries its own output-token budget and length instruction (Strategy).
 *
 * @see https://refactoring.guru/design-patterns/strategy
 */
enum AnswerDepth: string
{
    case Quick = 'quick';
    case Plan = 'plan';
    case Explain = 'explain';

    private const string ASKS_WHY_OR_HOW = '/\b(why|how (does|do|is|come|can)|what (does|is|are|happens)|explain|waarom|hoe (werkt|kan|komt)|wat (doet|is))\b/iu';

    private const string ASKS_WHAT_TO_DO = '/\b(plan|routine|session|workout|program|schedule|schema|suggest|exercises?|oefeningen|what (should|do|can|could|would) i (do|train|lift)|what (to )?do|wat (moet|kan|zal) ik doen|what weight|which weight|welk gewicht|how (heavy|much weight)|weight per|hoeveel kilo|\d+\s*(min|minutes|mins|minuten)\b|\d+\s*(things|exercises|moves|dingen))\b|\bsuperset\b/iu';

    public static function for(string $question): self
    {
        return match (true) {
            (bool) preg_match(self::ASKS_WHY_OR_HOW, $question) => self::Explain,
            (bool) preg_match(self::ASKS_WHAT_TO_DO, $question) => self::Plan,
            default => self::Quick,
        };
    }

    /**
     * Output budget. Generous on purpose: thinking models count their
     * reasoning against it, and a cut-off answer is worse than a long one.
     */
    public function maxTokens(): int
    {
        return match ($this) {
            self::Quick => (int) config('coach.max_tokens.quick', 800),
            self::Plan => (int) config('coach.max_tokens.plan', 1500),
            self::Explain => (int) config('coach.max_tokens.explain', 2000),
        };
    }

    public function instruction(): string
    {
        return match ($this) {
            self::Quick => 'This is a quick question: answer in at most 5 short lines, the most useful thing first.',
            self::Plan => 'They want to know what to do. Give a session they can walk in with: one line naming what today is for given their last 7 days and their limitation, a warm-up line, then numbered exercises with sets x reps and a load in kg from their numbers (or a starting rule when there is no record), fitting the time or count they gave. If the question is narrower than a session (a weight for one exercise, a day off or not), answer that with the same specifics and no padding.',
            self::Explain => 'They want to understand: explain plainly in at most 12 short lines, with one concrete picture or comparison, tied to their own situation in CONTEXT.',
        };
    }
}
