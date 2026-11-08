<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Channels\ChatChannel;
use App\Conversation\RespondToText;
use App\Exceptions\AiUnavailable;
use App\Models\WaitingMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Retries a kept message on the same path as a live one and replies in the
 * chat by itself. Waits longer between each try (config
 * conversation.retry_delays); after the last one it says so, quoting the
 * message, instead of going silent.
 */
final class ProcessWaitingMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

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

    public function handle(RespondToText $respond, ChatChannel $channel): void
    {
        $waiting = $this->waiting->fresh();

        if ($waiting === null || $waiting->status !== WaitingMessage::WAITING) {
            return;
        }

        $waiting->increment('attempts');

        try {
            $reply = $respond->reply($waiting->user, $waiting->text, $waiting->source);
        } catch (AiUnavailable) {
            $this->tryAgainOrGiveUp($waiting, $channel);

            return;
        }

        $waiting->update(['status' => WaitingMessage::DONE, 'handled_at' => now()]);
        $channel->send($waiting->chat_id, "About \"{$waiting->excerpt()}\":\n\n{$reply}");
    }

    private function tryAgainOrGiveUp(WaitingMessage $waiting, ChatChannel $channel): void
    {
        if ($waiting->attempts < self::maxTries()) {
            $this->release(self::delayBefore($waiting->attempts + 1));

            return;
        }

        $waiting->update(['status' => WaitingMessage::FAILED, 'handled_at' => now()]);
        $channel->send($waiting->chat_id, "I still could not process \"{$waiting->excerpt()}\" after several tries. Send it again when you like.");
    }
}
