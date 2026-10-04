<?php

declare(strict_types=1);

namespace App\Channels\Telegram;

use App\Channels\InboundMessage;
use App\Contracts\Channels\ChatChannel;

/**
 * Telegram adapter: sends replies and turns raw updates into InboundMessages.
 */
final readonly class TelegramChannel implements ChatChannel
{
    public const string PROVIDER = 'telegram';

    public function __construct(
        private TelegramClient $client,
    ) {}

    public function send(string $chatId, string $text): void
    {
        $this->client->sendMessage($chatId, $text);
    }

    /**
     * A text message in a private chat, else null (groups, edits, stickers, ...).
     *
     * @param  array<string, mixed>  $update
     */
    public static function inbound(array $update): ?InboundMessage
    {
        $message = $update['message'] ?? null;

        if (! is_array($message)
            || ($message['chat']['type'] ?? null) !== 'private'
            || ! is_string($message['text'] ?? null)
            || ! isset($message['from']['id'], $message['chat']['id'])) {
            return null;
        }

        return new InboundMessage(
            provider: self::PROVIDER,
            senderId: (string) $message['from']['id'],
            chatId: (string) $message['chat']['id'],
            text: $message['text'],
            senderName: $message['from']['first_name'] ?? null,
        );
    }
}
