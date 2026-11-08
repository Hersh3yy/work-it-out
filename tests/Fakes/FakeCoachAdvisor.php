<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Coach\AnswerDepth;
use App\Coach\CoachContext;
use App\Contracts\Ai\CoachAdvisor;
use App\Enums\TrainerPersona;

/**
 * Coach for tests: a fixed answer, every call recorded with the context it
 * was given, or an outage.
 */
final class FakeCoachAdvisor implements CoachAdvisor
{
    use FailsOnRequest;

    /** @var list<array{context: array<string, mixed>, persona: TrainerPersona, question: string, depth: AnswerDepth}> */
    public array $calls = [];

    public function __construct(private string $reply = '1. Chest-supported row 3x12') {}

    public function answer(CoachContext $context, TrainerPersona $persona, string $question, AnswerDepth $depth): string
    {
        $this->calls[] = ['context' => $context->toArray(), 'persona' => $persona, 'question' => $question, 'depth' => $depth];

        $this->failIfAsked();

        return $this->reply;
    }

    public function modelName(): ?string
    {
        return 'fake-coach';
    }
}
