<?php

declare(strict_types=1);

namespace App\Ai\SystemOne;

/**
 * A typed choice from a System One model: the pick, how sure, and the full
 * distribution over every option (they sum to about 1).
 */
final readonly class ChoiceAnswer
{
    /**
     * @param  array<string, float>  $probabilities
     */
    public function __construct(
        public string $choice,
        public float $confidence,
        public array $probabilities,
    ) {}
}
