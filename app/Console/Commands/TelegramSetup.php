<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\Telegram\TelegramClient;
use App\Channels\Telegram\TelegramUnavailable;
use Illuminate\Console\Command;

/**
 * Checks the bot token and sets the command menu Telegram shows next to the
 * message box. Safe to run again.
 */
final class TelegramSetup extends Command
{
    /** @var list<array{command: string, description: string}> */
    public const array COMMANDS = [
        ['command' => 'help', 'description' => 'How to log a workout'],
        ['command' => 'undo', 'description' => 'Remove your last log'],
        ['command' => 'me', 'description' => 'Your profile and notes'],
    ];

    protected $signature = 'channel:telegram:setup';

    protected $description = 'Check the Telegram bot token and set its command menu';

    public function handle(TelegramClient $telegram): int
    {
        if (blank(config('services.telegram.bot_token'))) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env.');

            return self::FAILURE;
        }

        try {
            $bot = $telegram->getMe();
            $telegram->setMyCommands(self::COMMANDS);
        } catch (TelegramUnavailable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Bot @%s is ready (id %s). Command menu set: /help, /undo, /me.', $bot['username'] ?? '?', $bot['id'] ?? '?'));
        $this->line('Next: php artisan channel:invite you@example.com, then make bot.');

        return self::SUCCESS;
    }
}
