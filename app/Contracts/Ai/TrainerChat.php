<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Ai\Data\TrainerReply;
use App\Enums\TrainerPersona;
use App\Models\User;

/**
 * Port for the conversational AI trainer.
 *
 * Controllers depend on this interface, never on the Laravel AI SDK directly,
 * so the whole trainer can be swapped for a fake in tests or a different
 * provider in production without touching HTTP code.
 *
 * @see https://refactoring.guru/design-patterns/adapter
 */
interface TrainerChat
{
    /**
     * Send a message to the trainer, optionally continuing an existing
     * conversation or overriding the user's default coach persona.
     */
    public function send(
        User $user,
        string $message,
        ?string $conversationId = null,
        ?TrainerPersona $coach = null,
    ): TrainerReply;
}
