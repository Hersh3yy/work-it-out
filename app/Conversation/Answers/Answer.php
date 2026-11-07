<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

/**
 * What an answer handler made of a message: the reply, and the rest of the
 * message when it also says something else ("70kg, now what next?").
 */
final readonly class Answer
{
    public function __construct(
        public string $reply,
        public ?string $followUp = null,
    ) {}
}
