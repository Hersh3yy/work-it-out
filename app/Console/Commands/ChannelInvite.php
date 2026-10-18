<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Linking\IssueLinkCode;
use App\Channels\Telegram\TelegramClient;
use App\Channels\Telegram\TelegramUnavailable;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Invites someone: creates their user if the email is new and prints a
 * one-time Telegram link. Tapping it connects their Telegram account.
 */
final class ChannelInvite extends Command
{
    protected $signature = 'channel:invite
        {email : Their email (a user is created if it does not exist)}
        {--name= : Name for a newly created user}';

    protected $description = 'Create a one-time Telegram invite link for a user';

    public function handle(IssueLinkCode $issue, TelegramClient $telegram): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = User::create([
                'name' => $this->option('name') ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::password(32),
            ]);
            $this->info("Created user {$email}.");
        }

        $connected = $user->channelIdentities()->where('provider', 'telegram')->pluck('external_id');

        if ($connected->isNotEmpty()) {
            $this->warn(sprintf(
                '%s is already connected to Telegram (user %s). This link connects one more Telegram account; to replace the old one, run php artisan channel:unlink %s first.',
                $user->name,
                $connected->implode(', '),
                $email,
            ));
        }

        ['code' => $code, 'expires_at' => $expiresAt] = $issue->handle($user);

        $username = $this->botUsername($telegram);

        $this->newLine();
        if ($username !== null) {
            $this->line("Invite link for {$user->name}: <info>https://t.me/{$username}?start={$code}</info>");
        } else {
            $this->line("Invite code for {$user->name}: <info>{$code}</info>");
            $this->line("They open the bot and send: /start {$code}");
        }
        $this->line('Works once, until '.$expiresAt->format('Y-m-d H:i').'. Older links for this person no longer work.');

        return self::SUCCESS;
    }

    private function botUsername(TelegramClient $telegram): ?string
    {
        if (blank(config('services.telegram.bot_token'))) {
            $this->warn('TELEGRAM_BOT_TOKEN is not set, so no link can be built.');

            return null;
        }

        try {
            return $telegram->getMe()['username'] ?? null;
        } catch (TelegramUnavailable $e) {
            $this->warn($e->getMessage());

            return null;
        }
    }
}
