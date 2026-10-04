<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\MessageClassifier;
use App\Enums\MessageKind;
use App\Interpretation\Classification;
use App\Interpretation\RuleClassifier;
use RuntimeException;

/**
 * Classify step for tests: the real plain rules first, then a fixed kind
 * instead of the model (workout unless told otherwise).
 */
final class FakeMessageClassifier implements MessageClassifier
{
    /** @var list<string> */
    public array $modelCalls = [];

    private bool $shouldFail = false;

    public function __construct(
        private MessageKind $modelSays = MessageKind::Workout,
    ) {}

    public function says(MessageKind $kind): self
    {
        $this->modelSays = $kind;

        return $this;
    }

    public function failing(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    public function classify(string $text): Classification
    {
        $byRules = (new RuleClassifier)->classify($text);

        if ($byRules !== null) {
            return $byRules;
        }

        $this->modelCalls[] = $text;

        if ($this->shouldFail) {
            throw new RuntimeException('Fake classifier outage');
        }

        return new Classification($this->modelSays, 0.9, 'model');
    }
}
