<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\HandleInboundMessage;
use App\Channels\Telegram\TelegramChannel;
use App\Channels\Telegram\TelegramClient;
use App\Channels\Telegram\TelegramUnavailable;
use App\Contracts\Channels\ChatChannel;
use Illuminate\Console\Command;
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
    private const string OFFSET_KEY = 'telegram:poll:offset';

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
                    return self::FAILURE;
                }

                sleep(5);

                continue;
            }

            foreach ($updates as $update) {
                $offset = (int) $update['update_id'] + 1;
                Cache::forever(self::OFFSET_KEY, $offset);

                $this->process($update, $handler, $channel);
            }
        } while (! $once);

        return self::SUCCESS;
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

        if ($reply === null) {
            $this->warn(sprintf(
                'Ignored a message from unlinked Telegram user %s (%s). Link it: php artisan channel:link you@example.com %s',
                $inbound->senderId,
                $inbound->senderName ?? 'no name',
                $inbound->senderId,
            ));

            return;
        }

        $this->line(sprintf('[%s] replied: %s', now()->format('H:i'), Str::limit(str_replace("\n", ' | ', $reply), 100)));

        try {
            $channel->send($inbound->chatId, $reply);
        } catch (TelegramUnavailable $e) {
            $this->warn($e->getMessage());
        }
    }
}
