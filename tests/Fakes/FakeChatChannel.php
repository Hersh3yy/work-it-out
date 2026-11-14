<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Channels\ChatChannel;
use App\Exceptions\ChatUnavailable;
use App\Exceptions\RecipientUnreachable;

/**
 * Chat channel for tests: remembers every message the app sends on its own
 * (a retried message's reply, the morning message), and can refuse a chat
 * the way a real provider does.
 */
final class FakeChatChannel implements ChatChannel
{
    /** @var list<array{chat_id: string, text: string}> */
    public array $sent = [];

    /** @var array<string, ChatUnavailable> chat id => what sending there throws */
    private array $refusals = [];

    /** The person blocked the bot. */
    public function unreachable(string $chatId): self
    {
        $this->refusals[$chatId] = new RecipientUnreachable("Fake: chat {$chatId} blocked the bot");

        return $this;
    }

    /** The provider is down for this chat right now. */
    public function down(string $chatId): self
    {
        $this->refusals[$chatId] = new ChatUnavailable("Fake: chat {$chatId} is down");

        return $this;
    }

    public function send(string $chatId, string $text): void
    {
        if (isset($this->refusals[$chatId])) {
            throw $this->refusals[$chatId];
        }

        $this->sent[] = ['chat_id' => $chatId, 'text' => $text];
    }
}
