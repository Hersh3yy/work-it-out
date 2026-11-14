<?php

declare(strict_types=1);

namespace App\Channels;

use App\Contracts\Channels\ChatChannel;
use App\Enums\ChatProvider;

/**
 * Which adapter sends on which provider, from config/conversation.php
 * `channels`. A provider without one (the app, the simulator) gets nothing
 * sent on its own.
 */
final readonly class ChatChannels
{
    public function for(ChatProvider $provider): ?ChatChannel
    {
        $class = config('conversation.channels')[$provider->value] ?? null;

        return $class === null ? null : app($class);
    }
}
