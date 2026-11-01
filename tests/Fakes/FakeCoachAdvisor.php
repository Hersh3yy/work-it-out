<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Coach\AnswerDepth;
use App\Coach\CoachContext;
use App\Contracts\Ai\CoachAdvisor;
use App\Enums\TrainerPersona;
use RuntimeException;

/**
 * Coach for tests: a fixed answer, every call recorded with the context it
 * was given, or an outage.
 */
final class FakeCoachAdvisor implements CoachAdvisor
{
    /** @var list<array{context: array<string, mixed>, persona: TrainerPersona, question: string, depth: AnswerDepth}> */
    public array $calls = [];

    private bool $shouldFail = false;

    public function __construct(private string $reply = '1. Chest-supported row 3x12') {}

    public function failing(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    public function answer(CoachContext $context, TrainerPersona $persona, string $question, AnswerDepth $depth): string
    {
        $this->calls[] = ['context' => $context->toArray(), 'persona' => $persona, 'question' => $question, 'depth' => $depth];

        if ($this->shouldFail) {
            throw new RuntimeException('Fake AI outage');
        }

        return $this->reply;
    }

    public function modelName(): ?string
    {
        return 'fake-coach';
    }
}
