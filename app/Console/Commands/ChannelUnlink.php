<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ChatProvider;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Disconnects a person's chat accounts, for a new phone, a new Telegram
 * account, or a link that went to the wrong person. Their logs stay; a new
 * invite connects them again.
 */
final class ChannelUnlink extends Command
{
    protected $signature = 'channel:unlink
        {email : The user whose chat accounts to disconnect}
        {--provider=telegram}';

    protected $description = 'Disconnect a user\'s Telegram (or other chat) accounts; their logs stay';

    public function handle(): int
    {
        $provider = ChatProvider::tryFrom((string) $this->option('provider'));
        $user = User::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if ($provider === null || $user === null) {
            $this->error($user === null ? 'No user with that email.' : 'Unknown provider.');

            return self::FAILURE;
        }

        $removed = $user->channelIdentities()->where('provider', $provider->value)->delete();

        $this->info(sprintf('Disconnected %d %s account(s) from %s. Their logs are kept. Reconnect with: php artisan channel:invite %s', $removed, $provider->value, $user->email, $user->email));

        return self::SUCCESS;
    }
}
