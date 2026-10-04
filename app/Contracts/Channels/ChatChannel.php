<?php

declare(strict_types=1);

namespace App\Contracts\Channels;

/**
 * Port for sending a reply on a chat provider (Telegram now; WhatsApp would
 * be one more adapter). The conversation core never knows which one.
 *
 * @see https://refactoring.guru/design-patterns/adapter
 */
interface ChatChannel
{
    public function send(string $chatId, string $text): void;
}
