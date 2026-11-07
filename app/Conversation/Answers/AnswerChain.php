<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Models\User;

/**
 * Runs the configured answer handlers in order.
 */
final readonly class AnswerChain
{
    public function handle(User $user, string $text): ?Answer
    {
        foreach (config('conversation.answer_handlers', []) as $class) {
            /** @var AnswerHandler $handler */
            $handler = app($class);
            $answer = $handler->handle($user, $text);

            if ($answer !== null) {
                return $answer;
            }
        }

        return null;
    }
}
