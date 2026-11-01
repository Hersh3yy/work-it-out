<?php

declare(strict_types=1);

namespace App\Coach;

/**
 * How much room a coach answer gets, decided in plain code from the
 * question (no extra AI call): a quick yes/no or tip, a session plan, or an
 * explanation of why or how something works. Each depth carries its own
 * output-token budget and length instruction (Strategy).
 *
 * @see https://refactoring.guru/design-patterns/strategy
 */
enum AnswerDepth: string
{
    case Quick = 'quick';
    case Plan = 'plan';
    case Explain = 'explain';

    private const string EXPLAIN = '/\b(why|how (does|do|is|come|can)|what (does|is|are|happens)|explain|waarom|hoe (werkt|kan|komt)|wat (doet|is))\b/iu';

    private const string PLAN = '/\b(plan|routine|session|workout|schedule|\d+\s*(min|minutes|mins)\b|\d+\s*(things|exercises|moves))|\bsuperset\b/iu';

    public static function for(string $question): self
    {
        return match (true) {
            (bool) preg_match(self::EXPLAIN, $question) => self::Explain,
            (bool) preg_match(self::PLAN, $question) => self::Plan,
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
            self::Quick => (int) config('coach.max_tokens.quick', 600),
            self::Plan => (int) config('coach.max_tokens.plan', 1000),
            self::Explain => (int) config('coach.max_tokens.explain', 2000),
        };
    }

    public function instruction(): string
    {
        return match ($this) {
            self::Quick => 'This is a quick question: answer in at most 3 short lines.',
            self::Plan => 'They want a session: a numbered list, one line per item with sets x reps or minutes, fitting the time or count they gave, and nothing else.',
            self::Explain => 'They want to understand: explain plainly in at most 10 short lines, with one concrete picture or comparison, tied to their own situation in CONTEXT.',
        };
    }
}
