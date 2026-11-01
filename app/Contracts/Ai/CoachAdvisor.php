<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Coach\AnswerDepth;
use App\Coach\CoachContext;
use App\Enums\TrainerPersona;
use App\Exceptions\AiUnavailable;

/**
 * Port for answering a question about the person's training from their
 * living context. The adapter words an answer; it stores nothing and the
 * answer never becomes a fact.
 *
 * @see https://refactoring.guru/design-patterns/adapter
 */
interface CoachAdvisor
{
    /**
     * @throws AiUnavailable
     */
    public function answer(CoachContext $context, TrainerPersona $persona, string $question, AnswerDepth $depth): string;

    /** The model name stored with each answer, for later comparison. */
    public function modelName(): ?string;
}
