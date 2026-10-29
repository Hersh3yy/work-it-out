<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Actions\SmartLog\AnswerOpenQuestion;
use App\Channels\ReplyComposer;
use App\Models\User;

/**
 * A number that fills the open question on the latest log ("30" for a
 * missing weight, "5" for sets or reps), or "skip" to leave it empty.
 */
final readonly class ExerciseQuestionAnswer implements AnswerHandler
{
    public function __construct(
        private AnswerOpenQuestion $answers,
        private ReplyComposer $replies,
    ) {}

    public function handle(User $user, string $text): ?string
    {
        $open = $this->answers->openFor($user);

        if ($open === null || ! str_starts_with((string) ($open->questions[0]['field'] ?? ''), 'exercises.')) {
            return null;
        }

        if (in_array(mb_strtolower(trim($text)), ['skip', '/skip'], true)) {
            $this->answers->skip($open);

            return 'OK, left it empty.';
        }

        $entry = $this->answers->answer($open, $text);

        return $entry === null ? null : 'Updated: '.$this->replies->entry($entry);
    }
}
