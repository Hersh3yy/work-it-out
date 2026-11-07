<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Actions\SmartLog\AnswerOpenQuestion;
use App\Channels\ReplyComposer;
use App\Interpretation\NumberReply;
use App\Models\User;

/**
 * A number that fills the open question on the latest log ("30" for a
 * missing weight, "5 reps each set"), or "skip" to leave it empty. Words
 * after the number that ask something else ("70kg, now what next?") are
 * handed back as a follow-up message.
 */
final readonly class ExerciseQuestionAnswer implements AnswerHandler
{
    public function __construct(
        private AnswerOpenQuestion $answers,
        private ReplyComposer $replies,
    ) {}

    public function handle(User $user, string $text): ?Answer
    {
        $open = $this->answers->openFor($user);

        if ($open === null || ! str_starts_with((string) ($open->questions[0]['field'] ?? ''), 'exercises.')) {
            return null;
        }

        if (in_array(mb_strtolower(trim($text)), ['skip', '/skip'], true)) {
            $this->answers->skip($open);

            return new Answer('OK, left it empty.');
        }

        $reply = NumberReply::from($text);
        $entry = $reply === null ? null : $this->answers->answer($open, $reply);

        return $entry === null ? null : new Answer('Updated: '.$this->replies->entry($entry), $reply->followUp());
    }
}
