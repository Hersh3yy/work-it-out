<?php

declare(strict_types=1);

namespace App\Limitations;

/**
 * The one screening question asked when a limitation is recorded. RedFlags
 * reacts to danger words someone happens to use; this asks, once, about the
 * signs that mean a nerve may be involved. Answered without AI by
 * App\Conversation\Answers\RedFlagScreenAnswer.
 */
final class RedFlagScreen
{
    public const string FIELD = 'limitation.red_flags';

    public const string QUESTION = 'One check: any numbness, tingling, weakness or pain running down an arm or leg? Reply yes or no.';

    public const string CLEAR_REPLY = "Good, thanks. I'll check with you when a logged exercise loads it, and the coach takes it into account. Tell me how it's going any time; say /better when it has healed.";

    public const string FLAGGED_REPLY = 'Thanks for telling me. Numbness, tingling, weakness or pain running down a limb can mean a nerve is involved, so please have it checked by a doctor or physio before you train it. Meanwhile I keep suggestions away from it.';

    /**
     * @return array{field: string, question: string, options: list<string>}
     */
    public static function question(): array
    {
        return ['field' => self::FIELD, 'question' => self::QUESTION, 'options' => ['yes', 'no']];
    }
}
