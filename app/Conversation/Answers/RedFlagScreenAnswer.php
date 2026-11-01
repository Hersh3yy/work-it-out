<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Actions\SmartLog\AnswerOpenQuestion;
use App\Events\LogRecorded;
use App\Limitations\RedFlagScreen;
use App\Models\Limitation;
use App\Models\User;

/**
 * The answer to the red-flag screen: yes (flagged) or no (clear), in English
 * or Dutch, or skip. Anything else is not an answer and goes on down the chain.
 */
final readonly class RedFlagScreenAnswer implements AnswerHandler
{
    private const array YES = ['yes', 'y', 'yeah', 'yep', 'ja', 'jawel', '1'];

    private const array NO = ['no', 'n', 'nope', 'none', 'nee', 'nothing', 'not really', '2'];

    public function __construct(
        private AnswerOpenQuestion $answers,
    ) {}

    public function handle(User $user, string $text): ?string
    {
        $open = $this->answers->openFor($user);

        if ($open === null || ($open->questions[0]['field'] ?? null) !== RedFlagScreen::FIELD || $open->limitation === null) {
            return null;
        }

        $answer = mb_strtolower(trim($text, " \t\n\r.!"));

        if (in_array($answer, ['skip', '/skip'], true)) {
            $open->update(['questions' => null]);

            return 'OK, skipped.';
        }

        $result = match (true) {
            in_array($answer, self::YES, true) => Limitation::RED_FLAGS_FLAGGED,
            in_array($answer, self::NO, true) => Limitation::RED_FLAGS_CLEAR,
            default => null,
        };

        if ($result === null) {
            return null;
        }

        $open->limitation->update(['red_flags' => $result]);
        $open->update(['questions' => null]);
        LogRecorded::dispatch($open);

        return $result === Limitation::RED_FLAGS_FLAGGED ? RedFlagScreen::FLAGGED_REPLY : RedFlagScreen::CLEAR_REPLY;
    }
}
