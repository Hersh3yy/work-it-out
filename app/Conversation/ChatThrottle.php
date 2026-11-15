<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * How many messages that need the AI one person may send a minute
 * (config/conversation.php). One person forwarding dozens of messages would
 * otherwise use up the shared AI quota for everyone. Commands and answers to
 * open questions need no AI and are never counted.
 */
final readonly class ChatThrottle
{
    public const string REPLY = "That's a lot of messages at once. I handled the ones before this; send this one again in a minute.";

    private const int MINUTE = 60;

    public function allows(User $user): bool
    {
        return RateLimiter::attempt(
            'chat-ai:'.$user->id,
            (int) config('conversation.ai_messages_per_minute'),
            static fn (): bool => true,
            self::MINUTE,
        );
    }
}
