<?php

declare(strict_types=1);

namespace App\Contracts\Channels;

use App\Exceptions\ChatUnavailable;
use App\Exceptions\RecipientUnreachable;

/**
 * Port for sending a reply on a chat provider (Telegram now; WhatsApp would
 * be one more adapter). The conversation core never knows which one.
 *
 * @see https://refactoring.guru/design-patterns/adapter
 */
interface ChatChannel
{
    /**
     * @throws RecipientUnreachable when the person blocked the bot; stop sending
     * @throws ChatUnavailable when the provider did not take it this time
     */
    public function send(string $chatId, string $text): void;
}
