<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Channels\ChatChannels;
use App\Conversation\RespondToText;
use App\Exceptions\AiUnavailable;
use App\Exceptions\ChatUnavailable;
use App\Exceptions\RecipientUnreachable;
use App\Models\WaitingMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Retries a kept message on the same path as a live one and replies in the
 * chat by itself. Waits longer between each try (config
 * conversation.retry_delays); after the last one it says so, quoting the
 * message, instead of going silent.
 *
 * Safe to run twice: the row is claimed before the AI is asked, and the
 * reply is stored before it is sent, so a failed send only sends again. A
 * bug is not an outage: it ends the job at once (maxExceptions) and
 * failed() tells the person.
 */
final class ProcessWaitingMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public int $maxExceptions = 1;

    public function __construct(
        public WaitingMessage $waiting,
    ) {}

    /** Seconds to wait before the given try (1 is the first retry). */
    public static function delayBefore(int $try): int
    {
        $delays = config('conversation.retry_delays');

        return (int) ($delays[$try - 1] ?? end($delays));
    }

    public static function maxTries(): int
    {
        return count(config('conversation.retry_delays'));
    }

    public function handle(RespondToText $respond, ChatChannels $channels): void
    {
        $waiting = $this->waiting->fresh();

        if ($waiting?->status === WaitingMessage::ANSWERED) {
            $this->send($waiting, $channels);

            return;
        }

        if ($waiting === null || ! $waiting->claim()) {
            return;
        }

        try {
            $reply = $respond->reply($waiting->user, $waiting->text, $waiting->source);
        } catch (AiUnavailable) {
            $this->tryAgainOrGiveUp($waiting, $channels);

            return;
        }

        $waiting->update(['status' => WaitingMessage::ANSWERED, 'reply' => "About \"{$waiting->excerpt()}\":\n\n{$reply}"]);
        $this->send($waiting, $channels);
    }

    public function failed(?Throwable $exception): void
    {
        $waiting = $this->waiting->fresh();

        if ($waiting === null || $waiting->isClosed()) {
            return;
        }

        $wasAnswered = $waiting->status === WaitingMessage::ANSWERED;
        $waiting->update(['status' => WaitingMessage::FAILED, 'handled_at' => now()]);

        if (! $wasAnswered) {
            $this->tell($waiting, app(ChatChannels::class), "I could not process \"{$waiting->excerpt()}\" because of a fault on my side. Send it again when you like.");
        }
    }

    private function send(WaitingMessage $waiting, ChatChannels $channels): void
    {
        try {
            $channels->for($waiting->provider)?->send($waiting->chat_id, (string) $waiting->reply);
        } catch (RecipientUnreachable) {
            $waiting->update(['status' => WaitingMessage::FAILED, 'handled_at' => now()]);

            return;
        } catch (ChatUnavailable) {
            $this->release(self::delayBefore(1));

            return;
        }

        $waiting->update(['status' => WaitingMessage::DONE, 'handled_at' => now()]);
    }

    private function tryAgainOrGiveUp(WaitingMessage $waiting, ChatChannels $channels): void
    {
        if ($waiting->attempts < self::maxTries()) {
            $waiting->update(['status' => WaitingMessage::WAITING]);
            $this->release(self::delayBefore($waiting->attempts + 1));

            return;
        }

        $waiting->update(['status' => WaitingMessage::FAILED, 'handled_at' => now()]);
        $this->tell($waiting, $channels, "I still could not process \"{$waiting->excerpt()}\" after several tries. Send it again when you like.");
    }

    private function tell(WaitingMessage $waiting, ChatChannels $channels, string $text): void
    {
        try {
            $channels->for($waiting->provider)?->send($waiting->chat_id, $text);
        } catch (ChatUnavailable) {
            logger()->warning('Could not tell the person about a kept message', ['waiting_message_id' => $waiting->id]);
        }
    }
}
