<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Usage\ModelPrice;

/**
 * One model's answer in a coach:try comparison, or why it gave none.
 */
final readonly class CoachTrialAnswer
{
    public function __construct(
        public string $label,
        public string $answer,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $ms = 0,
        public ?string $model = null,
        public ?string $error = null,
    ) {}

    public static function failed(string $label, string $error, int $ms): self
    {
        return new self(label: $label, answer: '', ms: $ms, error: $error);
    }

    public function cost(): ?float
    {
        return ModelPrice::cost($this->model, $this->inputTokens, $this->outputTokens);
    }
}
