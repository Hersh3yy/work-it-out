<?php

declare(strict_types=1);

namespace App\Limitations;

/**
 * Symptoms that always get "see a doctor now", before any AI reads the
 * message and with nothing saved. A fixed rule, never a model's decision.
 */
final class RedFlags
{
    public const string REPLY = "That sounds like something a doctor should look at today, not something to train through. Please contact a doctor or emergency services now.\n\nI haven't saved anything.";

    /** @var list<string> */
    private const array PATTERNS = [
        '/\bnumb(ness)?\b/iu',
        '/\btingl/iu',
        '/pain (shooting|going|running|radiating) down/iu',
        '/\b(leg|arm)s? (went|going|goes) numb/iu',
        '/\b(bladder|bowel)\b/iu',
        '/\bchest pain\b/iu',
        '/\bcan(no|\')?t (move|feel|walk|stand)\b/iu',
        '/\bcollapsed\b/iu',
        '/\bpassed out\b/iu',
        '/\bfaint(ed|ing)?\b/iu',
        '/\bblood in\b/iu',
        '/\b(gevoelloos|tintel|incontinent|pijn op de borst)/iu',
    ];

    public static function matches(string $text): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }
}
