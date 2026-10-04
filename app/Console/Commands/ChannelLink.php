<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ChatProvider;
use App\Models\ChannelIdentity;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Links a chat account to a user, creating the user when the email is new.
 * Linking is the allowlist: unlinked senders are ignored.
 */
final class ChannelLink extends Command
{
    protected $signature = 'channel:link
        {email : The user\'s email (created if it does not exist)}
        {external_id : The numeric chat user id, e.g. the Telegram user id}
        {--provider=telegram}
        {--name= : Name for a newly created user}';

    protected $description = 'Link a chat account (Telegram user id) to a user';

    public function handle(): int
    {
        $provider = ChatProvider::tryFrom((string) $this->option('provider'));

        if ($provider === null) {
            $this->error('Unknown provider. Use one of: '.implode(', ', array_column(ChatProvider::cases(), 'value')).'.');

            return self::FAILURE;
        }

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

        ChannelIdentity::query()->updateOrCreate(
            ['provider' => $provider, 'external_id' => (string) $this->argument('external_id')],
            ['user_id' => $user->id],
        );

        $this->info(sprintf('Linked %s user %s to %s.', $provider->value, $this->argument('external_id'), $email));

        return self::SUCCESS;
    }
}
