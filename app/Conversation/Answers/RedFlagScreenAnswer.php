<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Actions\Limitations\AnswerRedFlagScreen;
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
        private OpenQuestions $openQuestions,
        private AnswerRedFlagScreen $answerScreen,
    ) {}

    public function handle(User $user, string $text): ?Answer
    {
        $open = $this->openQuestions->for($user);
        $limitation = $open?->log->limitation;

        if ($open === null || ! $open->is(OpenQuestionKind::RedFlagCheck) || $limitation === null) {
            return null;
        }

        $answer = mb_strtolower(trim($text, " \t\n\r.!"));

        if (in_array($answer, ['skip', '/skip'], true)) {
            $open->log->update(['questions' => null]);

            return new Answer('OK, skipped.');
        }

        $result = match (true) {
            in_array($answer, self::YES, true) => Limitation::RED_FLAGS_FLAGGED,
            in_array($answer, self::NO, true) => Limitation::RED_FLAGS_CLEAR,
            default => null,
        };

        if ($result === null) {
            return null;
        }

        $this->answerScreen->handle($open->log, $limitation, $result);

        return new Answer($result === Limitation::RED_FLAGS_FLAGGED ? RedFlagScreen::FLAGGED_REPLY : RedFlagScreen::CLEAR_REPLY);
    }
}
