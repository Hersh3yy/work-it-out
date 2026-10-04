<?php

declare(strict_types=1);

namespace App\Interpretation;

use App\Enums\MessageKind;

/**
 * The classify step's answer: the kind, how sure, and who decided.
 */
final readonly class Classification
{
    public function __construct(
        public MessageKind $kind,
        public float $confidence,
        public string $decidedBy,
    ) {}

    /**
     * @return array{kind: string, confidence: float, decided_by: string}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'confidence' => round($this->confidence, 2),
            'decided_by' => $this->decidedBy,
        ];
    }
}
