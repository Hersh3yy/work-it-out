<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\Telegram\TelegramClient;
use App\Channels\Telegram\TelegramUnavailable;
use App\Conversation\Commands\ChatCommands;
use Illuminate\Console\Command;

/**
 * Checks the bot token and sets the command menu Telegram shows next to the
 * message box, from the commands in config/conversation.php. Safe to run again.
 */
final class TelegramSetup extends Command
{
    protected $signature = 'channel:telegram:setup';

    protected $description = 'Check the Telegram bot token and set its command menu';

    public function handle(TelegramClient $telegram, ChatCommands $commands): int
    {
        if (blank(config('services.telegram.bot_token'))) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env.');

            return self::FAILURE;
        }

        try {
            $bot = $telegram->getMe();
            $menu = $commands->menu();
            $telegram->setMyCommands($menu);
        } catch (TelegramUnavailable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Bot @%s is ready (id %s). Command menu set: %s.', $bot['username'] ?? '?', $bot['id'] ?? '?', implode(', ', array_map(static fn (array $c): string => '/'.$c['command'], $menu))));
        $this->line('Next: php artisan channel:invite you@example.com, then make bot.');

        return self::SUCCESS;
    }
}
