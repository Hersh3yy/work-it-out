<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Models\User;

/**
 * Runs the configured answer handlers in order.
 */
final readonly class AnswerChain
{
    public function handle(User $user, string $text): ?string
    {
        foreach (config('conversation.answer_handlers', []) as $class) {
            /** @var AnswerHandler $handler */
            $handler = app($class);
            $reply = $handler->handle($user, $text);

            if ($reply !== null) {
                return $reply;
            }
        }

        return null;
    }
}
