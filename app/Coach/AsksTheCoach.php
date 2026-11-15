<?php

declare(strict_types=1);

namespace App\Coach;

/**
 * Does a message that logs something also ask the coach something?
 * "bench 3x5 70, what next?", "...does your advice change". Plain code: the
 * receipt comes first, then the coach answers the same message.
 */
final readonly class AsksTheCoach
{
    /** Words that ask for advice on their own, with or without a question mark. */
    private const string ASKS = '/\b(suggest(ion)?s?|advise|advice|recommend(ation)?s?|what next|what(\'s| is) next|what after|now what|what should|what do i do|what to do|next\s*(step|move|exercise)|wat nu|wat moet ik)\b/iu';

    /** A question mark counts after a question word; "squat 5x5 100?" doubts a number, it does not ask the coach. */
    private const string QUESTION = '/\b(what|how|why|when|which|should|shall|can|could|would|will|is|are|am|do|does|did|any|wat|hoe|waarom|moet|kan|mag)\b[^.!?\n]*\?/iu';

    /** A tag at the end asks too: "the lower the better right?", "3x5 is enough, correct?". */
    private const string TAG_QUESTION = '/\b(right|correct|ok|okay|isn\'?t it|no|yes)\s*\?/iu';

    public static function in(string $text): bool
    {
        return (bool) preg_match(self::ASKS, $text)
            || (bool) preg_match(self::QUESTION, $text)
            || (bool) preg_match(self::TAG_QUESTION, $text);
    }
}
