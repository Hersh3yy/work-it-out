<?php

declare(strict_types=1);

namespace App\Ai\Data;

use App\Enums\TrainerPersona;

/**
 * Immutable value object returned by any TrainerChat implementation.
 */
final readonly class TrainerReply
{
    public function __construct(
        public string $reply,
        public ?string $conversationId,
        public TrainerPersona $coach,
    ) {}
}
