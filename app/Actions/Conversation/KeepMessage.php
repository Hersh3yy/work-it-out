<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Channels\InboundMessage;
use App\Jobs\ProcessWaitingMessage;
use App\Models\User;
use App\Models\WaitingMessage;

/**
 * Keeps a message the AI could not handle and schedules the retry, so the
 * person does not have to send it again (and does not count it twice when
 * they do: the front desk marks the kept copy as resent).
 */
final readonly class KeepMessage
{
    public const string REPLY = 'The AI is busy right now. I kept your message and will answer here in a few minutes, no need to send it again.';

    public function handle(User $user, InboundMessage $message, string $text): WaitingMessage
    {
        $waiting = $user->waitingMessages()->create([
            'provider' => $message->provider,
            'chat_id' => $message->chatId,
            'source' => $message->provider->logSource(),
            'text' => $text,
            'status' => WaitingMessage::WAITING,
        ]);

        ProcessWaitingMessage::dispatch($waiting)->delay(now()->addSeconds(ProcessWaitingMessage::delayBefore(1)));

        return $waiting;
    }

    /** The same text sent again while its kept copy waits: handle the new one, drop the old. */
    public function markResent(User $user, string $text): void
    {
        $user->waitingMessages()->waiting()->where('text', $text)->update(['status' => WaitingMessage::RESENT]);
    }
}
