<?php

declare(strict_types=1);

namespace App\Interpretation;

/**
 * A reply that starts with a number, read without AI: "70", "5 reps each
 * set", "about 32.5 kg", "70kg....now what next?". The words after the number
 * are either part of the answer ("each set") or a message of their own
 * ("now what next?"). A second number means it is not an answer at all but a
 * new log ("3x5 squat 100").
 */
final readonly class NumberReply
{
    private const string LEADING_WORDS = '(?:it was|that was|was|about|around|roughly|maybe|like|i did|did)';

    private const string PART_OF_THE_ANSWER = '/^(?:reps?|kgs?|kilos?|each( set)?|per set|every set|all( sets| of them)?|(?:for|on) (?:all|each|every) sets?|thanks?|thx|ok)?$/';

    private function __construct(
        public float $value,
        public string $unit,
        private string $rest,
    ) {}

    public static function from(string $text): ?self
    {
        $text = mb_strtolower(trim($text));

        if (! preg_match('/^(?:'.self::LEADING_WORDS.'\s+)?(\d+(?:[.,]\d+)?)\s*([a-z]*)(.*)$/su', $text, $m)) {
            return null;
        }

        $rest = trim(ltrim($m[3], " \t\n\r.,!;:-…"));

        if (preg_match('/\d/', $rest)) {
            return null;
        }

        return new self((float) str_replace(',', '.', $m[1]), $m[2], $rest);
    }

    public function isWholeAnswer(): bool
    {
        return (bool) preg_match(self::PART_OF_THE_ANSWER, rtrim($this->rest, ' .!?'));
    }

    /** The words after the answer that are a message of their own, or null. */
    public function followUp(): ?string
    {
        return $this->isWholeAnswer() ? null : $this->rest;
    }
}
