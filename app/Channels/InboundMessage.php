<?php

declare(strict_types=1);

namespace App\Channels;

use App\Enums\ChatProvider;

/**
 * One text message from any chat provider, already normalized.
 */
final readonly class InboundMessage
{
    public function __construct(
        public ChatProvider $provider,
        public string $senderId,
        public string $chatId,
        public string $text,
        public ?string $senderName = null,
    ) {}
}
