<?php

declare(strict_types=1);

namespace App\Services\Profile;

/**
 * Immutable snapshot of how complete a user's training profile is.
 */
final readonly class ProfileIntakeReport
{
    /**
     * @param  array<string, string>  $missing  field name => question a coach should ask
     */
    public function __construct(
        public int $percentComplete,
        public array $missing,
    ) {}

    public function isComplete(): bool
    {
        return $this->missing === [];
    }

    public function nextQuestion(): ?string
    {
        $questions = array_values($this->missing);

        return $questions[0] ?? null;
    }

    /**
     * @return array{percent_complete: int, is_complete: bool, missing_fields: list<string>, questions: array<string, string>, next_question: string|null}
     */
    public function toArray(): array
    {
        return [
            'percent_complete' => $this->percentComplete,
            'is_complete' => $this->isComplete(),
            'missing_fields' => array_keys($this->missing),
            'questions' => $this->missing,
            'next_question' => $this->nextQuestion(),
        ];
    }
}
