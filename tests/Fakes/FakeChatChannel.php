<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Channels\ChatChannel;

/**
 * Chat channel for tests: remembers every message the app sends on its own
 * (a retried message's reply, the morning message).
 */
final class FakeChatChannel implements ChatChannel
{
    /** @var list<array{chat_id: string, text: string}> */
    public array $sent = [];

    public function send(string $chatId, string $text): void
    {
        $this->sent[] = ['chat_id' => $chatId, 'text' => $text];
    }
}
