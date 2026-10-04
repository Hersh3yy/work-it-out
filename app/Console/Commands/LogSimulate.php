<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\HandleInboundMessage;
use App\Channels\InboundMessage;
use App\Enums\ChatProvider;
use App\Models\ChannelIdentity;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Gym time without a phone: runs messages through the exact chat path
 * (parse, record, reply) and prints what the phone would show. One message
 * as an argument, or a file with one message per line (# comments, blank
 * lines skipped) to replay a stored session.
 */
final class LogSimulate extends Command
{
    protected $signature = 'log:simulate
        {text? : One message, as you would text it}
        {--user=1 : The user id to log as}
        {--file= : Replay a file with one message per line}';

    protected $description = 'Send messages through the chat path and print the replies, no Telegram';

    public function handle(HandleInboundMessage $handler): int
    {
        $user = User::query()->find((int) $this->option('user'));

        if ($user === null) {
            $this->error("No user with id {$this->option('user')}. Create one: php artisan channel:link you@example.com 1");

            return self::FAILURE;
        }

        $messages = $this->messages();

        if ($messages === []) {
            $this->error('Give a message or --file=path.');

            return self::FAILURE;
        }

        ChannelIdentity::query()->firstOrCreate(
            ['provider' => ChatProvider::Simulate, 'external_id' => (string) $user->id],
            ['user_id' => $user->id],
        );

        foreach ($messages as $text) {
            $this->line("<comment>You:</comment> {$text}");

            $reply = $handler->handle(new InboundMessage(ChatProvider::Simulate, (string) $user->id, (string) $user->id, $text, $user->name));

            $this->line('<info>Bot:</info> '.Str::replace("\n", "\n     ", (string) $reply));
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function messages(): array
    {
        $text = $this->argument('text');

        if (is_string($text) && trim($text) !== '') {
            return [trim($text)];
        }

        $file = $this->option('file');

        if (! is_string($file) || ! is_file($file)) {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), file($file, FILE_IGNORE_NEW_LINES) ?: []),
            static fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'),
        ));
    }
}
