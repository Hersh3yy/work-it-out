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
    private const string ASKS = '/\?|\b(suggest(ion)?s?|advise|advice|recommend(ation)?s?|what next|what(\'s| is) next|what after|now what|what should|what do i do|what to do|next\s*(step|move|exercise)|wat nu|wat moet ik)\b/iu';

    public static function in(string $text): bool
    {
        return (bool) preg_match(self::ASKS, $text);
    }
}
