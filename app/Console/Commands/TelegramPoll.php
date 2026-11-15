<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\HandleInboundMessage;
use App\Channels\Telegram\TelegramChannel;
use App\Channels\Telegram\TelegramClient;
use App\Channels\Telegram\TelegramUnavailable;
use App\Contracts\Channels\ChatChannel;
use App\Exceptions\ChatUnavailable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Long-polls Telegram and answers each message. Runs anywhere with outbound
 * internet (the laptop, or a worker on the VPS); no public webhook needed.
 * Run exactly one poller per bot: Telegram refuses a second getUpdates.
 */
final class TelegramPoll extends Command
{
    public const string LOCK = 'telegram:poll:lock';

    private const string OFFSET_KEY = 'telegram:poll:offset';

    private const int LEASE_SECONDS = 120;

    private const int WAIT_SECONDS = 5;

    protected $signature = 'channel:telegram:poll
        {--once : Handle one batch of waiting updates and exit}
        {--timeout=30 : Long-poll timeout in seconds}';

    protected $description = 'Receive Telegram messages by long polling and reply';

    public function handle(TelegramClient $telegram, HandleInboundMessage $handler, ChatChannel $channel): int
    {
        if (blank(config('services.telegram.bot_token'))) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env.');

            return self::FAILURE;
        }

        $once = (bool) $this->option('once');
        $lease = $this->lease($once);

        if ($lease === null) {
            $this->error('Another poller is running for this bot; it holds the lock. Stop that one first.');

            return self::FAILURE;
        }

        try {
            $reached = $this->poll($telegram, $handler, $channel, $once, $lease);
        } finally {
            Cache::restoreLock(self::LOCK, $lease->owner())->release();
        }

        return $reached ? self::SUCCESS : self::FAILURE;
    }

    /**
     * One poller per bot (audit 2026-11-14 C4): during a deploy the old and
     * new container both start one. --once gives up at once; the long-running
     * poller waits for the lock, so supervisor does not mark it failed and
     * it takes over when the old container stops.
     */
    private function lease(bool $once): ?Lock
    {
        $lease = Cache::lock(self::LOCK, self::LEASE_SECONDS);
        $waiting = false;

        while (! $lease->get()) {
            if ($once) {
                return null;
            }

            if (! $waiting) {
                $this->warn('Another poller holds the lock; waiting for it to stop.');
                $waiting = true;
            }

            sleep(self::WAIT_SECONDS);
        }

        return $lease;
    }

    /** The lease ends after LEASE_SECONDS unless renewed, so a crashed poller frees it. */
    private function renew(Lock $lease): bool
    {
        Cache::restoreLock(self::LOCK, $lease->owner())->release();

        return Cache::lock(self::LOCK, self::LEASE_SECONDS, $lease->owner())->get();
    }

    /** False when --once could not reach Telegram. */
    private function poll(TelegramClient $telegram, HandleInboundMessage $handler, ChatChannel $channel, bool $once, Lock $lease): bool
    {
        $offset = Cache::get(self::OFFSET_KEY);

        if (! $once) {
            $this->info('Listening for Telegram messages. Ctrl+C to stop.');
        }

        do {
            try {
                $updates = $telegram->getUpdates($offset, $once ? 0 : (int) $this->option('timeout'));
            } catch (TelegramUnavailable $e) {
                $this->warn($e->getMessage());

                if ($once) {
                    return false;
                }

                sleep(self::WAIT_SECONDS);

                continue;
            }

            foreach ($updates as $update) {
                $offset = (int) $update['update_id'] + 1;
                Cache::forever(self::OFFSET_KEY, $offset);

                $this->process($update, $handler, $channel);
            }
        } while (! $once && $this->renew($lease));

        return true;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function process(array $update, HandleInboundMessage $handler, ChatChannel $channel): void
    {
        $inbound = TelegramChannel::inbound($update);

        if ($inbound === null) {
            return;
        }

        try {
            $reply = $handler->handle($inbound);
        } catch (Throwable $e) {
            report($e);
            $reply = 'Something went wrong on my side, so nothing was saved. Try again.';
        }

        if ($reply === null || $reply === HandleInboundMessage::STRANGER) {
            $this->warn(sprintf(
                'Message from unlinked Telegram user %s (%s). Invite them: php artisan channel:invite their@email',
                $inbound->senderId,
                $inbound->senderName ?? 'no name',
            ));
        }

        if ($reply === HandleInboundMessage::LINK_FAILED) {
            $this->warn(sprintf(
                'Invite link failed for Telegram user %s (%s): expired, used or mistyped. New link: php artisan channel:invite their@email',
                $inbound->senderId,
                $inbound->senderName ?? 'no name',
            ));
        }

        if ($reply === null) {
            return;
        }

        $this->line(sprintf('[%s] replied: %s', now()->format('H:i'), Str::limit(str_replace("\n", ' | ', $reply), 100)));

        try {
            $channel->send($inbound->chatId, $reply);
        } catch (ChatUnavailable $e) {
            $this->warn($e->getMessage());
        }
    }
}
