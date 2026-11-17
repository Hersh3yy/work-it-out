<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Models\User;
use App\Models\WaitingMessage;

/**
 * "Still busy?" while a message is kept for a retry: answered from the kept
 * message itself, never by the AI. On 16 Nov the question went to the injury
 * reader, timed out and was kept too. Without a kept message the text goes
 * on down the chain like any other.
 */
final readonly class KeptMessageStatusAnswer implements AnswerHandler
{
    private const string ASKS_ABOUT_THE_BOT = '/^\s*(still (busy|there|working)|busy|did you get (it|that|my message)|got (it|that|my message)|any (update|news)|hello|hallo|hi|you there|are you there|status)\s*[?!.]*\s*$/i';

    public function handle(User $user, string $text): ?Answer
    {
        if (preg_match(self::ASKS_ABOUT_THE_BOT, $text) !== 1) {
            return null;
        }

        $kept = $user->waitingMessages()
            ->whereIn('status', [WaitingMessage::WAITING, WaitingMessage::PROCESSING])
            ->oldest()
            ->first();

        return $kept === null ? null : new Answer($this->statusOf($kept));
    }

    private function statusOf(WaitingMessage $kept): string
    {
        $message = "Your message \"{$kept->excerpt()}\" from {$kept->created_at->format('H:i')}";

        return $kept->status === WaitingMessage::PROCESSING
            ? "{$message} is being handled right now; the answer comes here in a moment."
            : "{$message} is kept: the AI was busy. I try again at {$kept->nextTryAt()->format('H:i')} and answer here, no need to send it again.";
    }
}
